<?php

namespace App\Modules\Approval\Services;

use App\Core\DB;
use App\Core\Audit;
use App\Core\Mailer;
use PDO;

/**
 * Distinguishable from a generic RuntimeException so
 * advanceToNextStep() can catch specifically this case and NOT
 * roll back the approval that already happened.
 */
class NoEligibleApproverException extends \RuntimeException
{
}

class ApprovalService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    // ── APPROVE ──────────────────────────────────────────────

    public function approve(int $approvalId, int $userId, ?string $comment = null): int
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                SELECT
                    ga.id,
                    ga.status AS approval_status,
                    ga.workflow_instance_id,
                    ga.workflow_step_id,
                    gwi.workflow_id,
                    gwi.current_step_order,
                    gwi.status AS workflow_status,
                    gwi.gatepass_id
                FROM gatepass_approvals ga
                INNER JOIN gatepass_workflow_instances gwi
                    ON gwi.id = ga.workflow_instance_id
                WHERE ga.id = ? AND ga.approver_user_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$approvalId, $userId]);
            $approval = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$approval) {
                throw new \RuntimeException("Approval not found.");
            }
            if ($approval['approval_status'] !== 'pending') {
                throw new \RuntimeException("Already processed.");
            }
            if ($approval['workflow_status'] !== 'in_progress') {
                throw new \RuntimeException("Workflow not active.");
            }

            $instanceId  = (int) $approval['workflow_instance_id'];
            $currentStep = (int) $approval['current_step_order'];

            // Validate step order + read this step's approval rule
            // ('all' = unanimous, today's original-and-only behavior;
            // 'any' = first eligible approver resolves the step).
            $stmt = $this->db->prepare("
                SELECT step_order, approval_rule FROM workflow_steps WHERE id = ? AND workflow_id = ?
            ");
            $stmt->execute([$approval['workflow_step_id'], $approval['workflow_id']]);
            $stepRow      = $stmt->fetch(PDO::FETCH_ASSOC);
            $stepOrder    = (int) ($stepRow['step_order'] ?? 0);
            $approvalRule = $stepRow['approval_rule'] ?? 'all';

            if ($stepOrder !== $currentStep) {
                throw new \RuntimeException("Invalid approval step.");
            }

            // Mark approved
            $this->db->prepare("
                UPDATE gatepass_approvals SET status = 'approved', acted_at = NOW(), comments = ? WHERE id = ?
            ")->execute([$comment, $approvalId]);

            Audit::log(
                'gatepass.approval_approved',
                'gatepass',
                (int) $approval['gatepass_id'],
                ['approval_id' => $approvalId, 'workflow_instance_id' => $instanceId, 'step' => $currentStep]
            );

            if ($approvalRule === 'any') {
                // First eligible approver at this step resolves it —
                // close out any other still-pending approvals at the
                // same step instead of leaving them sitting in other
                // approvers' queues forever with nothing left to do.
                $this->db->prepare("
                    UPDATE gatepass_approvals ga
                    INNER JOIN workflow_steps ws ON ws.id = ga.workflow_step_id
                    SET ga.status = 'skipped', ga.acted_at = NOW(),
                        ga.comments = 'Skipped — already resolved by another approver at this step.'
                    WHERE ga.workflow_instance_id = ? AND ws.step_order = ? AND ga.status = 'pending'
                ")->execute([$instanceId, $currentStep]);

                $this->advanceToNextStep($instanceId);
                $this->db->commit();
                return $instanceId;
            }

            // 'all' — unanimous — every eligible approver at this
            // step must act before it advances.
            // Check if other approvals still pending at this step
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM gatepass_approvals ga
                INNER JOIN workflow_steps ws ON ws.id = ga.workflow_step_id
                WHERE ga.workflow_instance_id = ? AND ws.step_order = ? AND ga.status = 'pending'
            ");
            $stmt->execute([$instanceId, $currentStep]);

            if ((int) $stmt->fetchColumn() > 0) {
                $this->db->commit();
                return $instanceId;
            }

            $this->advanceToNextStep($instanceId);

            $this->db->commit();
            return $instanceId;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── REJECT ───────────────────────────────────────────────

    public function reject(int $approvalId, int $userId, string $comments): int
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                SELECT ga.id, ga.status, ga.workflow_instance_id,
                       gwi.status AS workflow_status, gwi.gatepass_id
                FROM gatepass_approvals ga
                INNER JOIN gatepass_workflow_instances gwi
                    ON ga.workflow_instance_id = gwi.id
                WHERE ga.id = ? AND ga.approver_user_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$approvalId, $userId]);
            $approval = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$approval) {
                throw new \RuntimeException("Approval not found.");
            }
            if ($approval['status'] !== 'pending') {
                throw new \RuntimeException("Already processed.");
            }
            if ($approval['workflow_status'] !== 'in_progress') {
                throw new \RuntimeException("Workflow not active.");
            }

            $instanceId = (int) $approval['workflow_instance_id'];
            $gatepassId = (int) $approval['gatepass_id'];

            $this->db->prepare("
                UPDATE gatepass_approvals
                SET status = 'rejected', acted_at = NOW(), comments = ?
                WHERE id = ?
            ")->execute([$comments, $approvalId]);

            Audit::log(
                'gatepass.approval_rejected',
                'gatepass',
                $gatepassId,
                ['approval_id' => $approvalId, 'workflow_instance_id' => $instanceId, 'comments' => $comments]
            );

            $this->db->prepare("
                UPDATE gatepass_workflow_instances
                SET status = 'rejected', completed_at = NOW()
                WHERE id = ?
            ")->execute([$instanceId]);

            // Anything else still pending on this instance (siblings
            // at the same step under an 'all' rule, or any lingering
            // row) no longer has anything to act on — the request is
            // dead. Without this they'd sit in another approver's
            // queue indefinitely with no explanation.
            $this->db->prepare("
                UPDATE gatepass_approvals
                SET status = 'skipped', acted_at = NOW(),
                    comments = 'Skipped — request was rejected by another approver.'
                WHERE workflow_instance_id = ? AND status = 'pending'
            ")->execute([$instanceId]);

            $stmt = $this->db->prepare("SELECT id FROM gatepass_statuses WHERE name = 'Rejected' LIMIT 1");
            $stmt->execute([]);
            $statusId = $stmt->fetchColumn();

            if (!$statusId) {
                throw new \RuntimeException("Rejected status not configured.");
            }

            $this->db->prepare("UPDATE gatepasses SET status_id = ? WHERE id = ?")
                     ->execute([$statusId, $gatepassId]);

            $this->db->commit();
            return $instanceId;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── ADVANCE STEP ─────────────────────────────────────────

    private function advanceToNextStep(int $instanceId): void
    {
        $stmt = $this->db->prepare("
            SELECT * FROM gatepass_workflow_instances WHERE id = ? FOR UPDATE
        ");
        $stmt->execute([$instanceId]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instance) {
            throw new \RuntimeException("Workflow instance not found.");
        }

        $currentStep = (int) $instance['current_step_order'];
        $nextStep    = $currentStep + 1;

        $stmt = $this->db->prepare("
            SELECT id FROM workflow_steps WHERE workflow_id = ? AND step_order = ? LIMIT 1
        ");
        $stmt->execute([$instance['workflow_id'], $nextStep]);
        $step = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$step) {
            // Final step → approved
            $this->db->prepare("
                UPDATE gatepass_workflow_instances
                SET status = 'approved', completed_at = NOW()
                WHERE id = ?
            ")->execute([$instanceId]);

            $this->db->prepare("
                UPDATE gatepasses
                SET status_id = (SELECT id FROM gatepass_statuses WHERE name = 'Approved' LIMIT 1)
                WHERE id = ?
            ")->execute([$instance['gatepass_id']]);

            return;
        }

        $this->db->prepare("
            UPDATE gatepass_workflow_instances SET current_step_order = ? WHERE id = ?
        ")->execute([$nextStep, $instanceId]);

        try {
            $this->createApprovalsForStep($instanceId, $nextStep);
        } catch (NoEligibleApproverException $e) {
            // Don't let this roll back the approval that just happened —
            // that approval is real and should stick. The workflow
            // legitimately advanced to $nextStep; it's just stalled
            // there until someone is assigned the required role in
            // the required department. Detectable via
            // getStalledInstances() below (no pending approvals exist
            // for the instance's current step).
            Audit::log(
                'gatepass.workflow_stalled',
                'gatepass',
                (int) $instance['gatepass_id'],
                ['workflow_instance_id' => $instanceId, 'stalled_at_step' => $nextStep, 'reason' => $e->getMessage()]
            );

            $this->notifyStall((int) $instance['gatepass_id'], $nextStep, $e->getMessage());
        }
    }

    /**
     * Best-effort email to everyone who can actually fix a stall
     * (same audience as getStalledInstances()'s visibility —
     * settings.update holders) — previously the ONLY way to learn a
     * workflow had stalled was to notice it on the Approvals page,
     * which meant it could sit silently indefinitely. A mail failure
     * (bad SMTP config, etc.) must never break the approval that
     * triggered this — same "never block the core flow on a
     * notification" principle used elsewhere in the app.
     */
    private function notifyStall(int $gatepassId, int $stalledStep, string $reason): void
    {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT u.email, u.first_name
                FROM users u
                INNER JOIN user_roles ur       ON ur.user_id = u.id
                INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
                INNER JOIN permissions p       ON p.id = rp.permission_id
                INNER JOIN modules m           ON m.id = p.module_id
                INNER JOIN actions a           ON a.id = p.action_id
                WHERE u.is_active = 1 AND u.email != '' AND m.name = 'settings' AND a.name = 'update'
            ");
            $stmt->execute();
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$recipients) {
                return;
            }

            $stmt = $this->db->prepare("SELECT gatepass_number FROM gatepasses WHERE id = ?");
            $stmt->execute([$gatepassId]);
            $gatepassNumber = $stmt->fetchColumn() ?: "#{$gatepassId}";

            $subject = "Gatepass {$gatepassNumber} approval is stalled";
            $body = '<p>Gatepass <strong>' . htmlspecialchars($gatepassNumber) . '</strong> has stopped at step '
                . (int) $stalledStep . ' of its approval workflow — no eligible approver could be found.</p>'
                . '<p><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>'
                . '<p>Assign an eligible approver under Settings &rarr; Workflows &rarr; Steps &rarr; Assign '
                . 'Approvers (or check the required role/department for a role-based step) to let it continue.</p>';

            foreach ($recipients as $recipient) {
                Mailer::send($recipient['email'], $subject, $body);
            }
        } catch (\Throwable $e) {
            error_log('ApprovalService::notifyStall failed: ' . $e->getMessage());
        }
    }

    /**
     * Workflow instances that have advanced to a step with nobody
     * eligible to approve it — stuck until an admin assigns the
     * required role to someone in the required department. Surface
     * this somewhere an admin will actually see it (e.g. the
     * Approvals or Settings dashboard) rather than leaving the
     * gatepass creator wondering why nothing is happening.
     */
    public function getStalledInstances(): array
    {
        return $this->db->query("
            SELECT gwi.id, gwi.gatepass_id, gwi.current_step_order, gwi.workflow_id,
                   ws.name AS step_name, ws.role_id, ws.department_id AS step_department_id,
                   g.gatepass_number, g.department_id AS gatepass_department_id,
                   r.name AS role_name
            FROM gatepass_workflow_instances gwi
            INNER JOIN workflow_steps ws
                ON ws.workflow_id = gwi.workflow_id AND ws.step_order = gwi.current_step_order
            INNER JOIN gatepasses g ON g.id = gwi.gatepass_id
            LEFT JOIN roles r ON r.id = ws.role_id
            WHERE gwi.status = 'in_progress'
              AND NOT EXISTS (
                  SELECT 1 FROM gatepass_approvals ga
                  WHERE ga.workflow_instance_id = gwi.id
                    AND ga.workflow_step_id = ws.id
                    AND ga.status = 'pending'
              )
            ORDER BY gwi.started_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── CREATE APPROVALS FOR STEP ────────────────────────────

private function createApprovalsForStep(int $instanceId, int $stepOrder): void
{
    // Fetch step + its own department_id and routing configuration
    $stmt = $this->db->prepare("
        SELECT ws.id AS step_id, ws.role_id, ws.step_order, ws.department_id AS step_dept_id,
               ws.assignment_type, ws.department_scope
        FROM workflow_steps ws
        INNER JOIN gatepass_workflow_instances gwi ON gwi.workflow_id = ws.workflow_id
        WHERE gwi.id = ? AND ws.step_order = ?
        LIMIT 1
    ");
    $stmt->execute([$instanceId, $stepOrder]);
    $step = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$step) {
        throw new \RuntimeException("Workflow step not found.");
    }

    // Fetched once, used by 'same_as_request' scoping below AND by
    // the segregation-of-duties exclusion further down — a gatepass
    // creator is never eligible to approve their own request, at
    // any step, under either assignment mode. Without this, someone
    // who both filed a request and happens to hold (or be tagged
    // for) the approving role/step could sign off on their own
    // gatepass.
    $stmt = $this->db->prepare("
        SELECT g.department_id, g.created_by
        FROM gatepasses g
        INNER JOIN gatepass_workflow_instances gwi ON gwi.gatepass_id = g.id
        WHERE gwi.id = ?
    ");
    $stmt->execute([$instanceId]);
    $gatepass = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['department_id' => null, 'created_by' => null];
    $requesterId = $gatepass['created_by'] !== null ? (int) $gatepass['created_by'] : 0;

    if ($step['assignment_type'] === 'explicit') {
        // Eligible approvers are an admin-picked list, tagged via
        // Settings → Workflows → Steps → Assign Approvers — entirely
        // department-agnostic by design (e.g. a General Manager who
        // approves company-wide regardless of which department they
        // personally sit in).
        $stmt = $this->db->prepare("
            SELECT u.id
            FROM workflow_step_approvers wsa
            INNER JOIN users u ON u.id = wsa.user_id
            WHERE wsa.workflow_step_id = ? AND u.is_active = 1 AND u.id != ?
        ");
        $stmt->execute([$step['step_id'], $requesterId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $users = $this->substituteDelegates($users, $requesterId);

        if (!$users) {
            throw new NoEligibleApproverException(
                "No active users are assigned as approvers for step {$step['step_id']} (excluding the " .
                "gatepass's own requester, who is never eligible to approve their own request) — " .
                "add some under Settings → Workflows → Steps → Assign Approvers."
            );
        }
    } else {
        // 'role_department' — dynamic matching by role, scoped by
        // department_scope:
        //   'same_as_request' — approver's department must match the
        //                       GATEPASS's own department (e.g. a
        //                       Department Head approving only their
        //                       own department's requests).
        //   'fixed'           — approver's department must match the
        //                       STEP's own configured department_id
        //                       (e.g. a dedicated desk that clears
        //                       every department's requests).
        //   'any'             — role match only, no department filter
        //                       at all.
        // Previously an unset step department silently behaved like
        // 'same_as_request' with no way to ask for 'any' — which is
        // exactly what stalled a cross-department approver (a role
        // whose holder's own department differs from the requester's)
        // with zero approvals ever created and no visible error to
        // the approver.
        $scope = $step['department_scope'] ?? 'same_as_request';

        $deptId = null;
        if ($scope === 'fixed') {
            $deptId = $step['step_dept_id'] ? (int) $step['step_dept_id'] : null;
        } elseif ($scope === 'same_as_request') {
            $deptId = $gatepass['department_id'] !== null ? (int) $gatepass['department_id'] : null;
        }
        // $scope === 'any' leaves $deptId as null → no department filter.

        if ($deptId !== null) {
            $stmt = $this->db->prepare("
                SELECT u.id
                FROM users u
                INNER JOIN user_roles ur ON ur.user_id = u.id
                WHERE ur.role_id = ? AND u.is_active = 1 AND u.department_id = ? AND u.id != ?
            ");
            $stmt->execute([$step['role_id'], $deptId, $requesterId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT u.id
                FROM users u
                INNER JOIN user_roles ur ON ur.user_id = u.id
                WHERE ur.role_id = ? AND u.is_active = 1 AND u.id != ?
            ");
            $stmt->execute([$step['role_id'], $requesterId]);
        }
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $users = $this->substituteDelegates($users, $requesterId);

        if (!$users) {
            throw new NoEligibleApproverException(
                "No active users found for role {$step['role_id']}" .
                ($deptId !== null ? " in department {$deptId}" : " (any department)") .
                " (excluding the gatepass's own requester, who is never eligible to approve their own request)."
            );
        }
    }

    foreach ($users as $user) {
        $this->db->prepare("
            INSERT INTO gatepass_approvals
                (workflow_instance_id, workflow_step_id, approver_user_id, status)
            VALUES (?, ?, ?, 'pending')
        ")->execute([$instanceId, $step['step_id'], $user['id']]);
    }
}

/**
 * Swaps any eligible approver who currently has an active delegate
 * (Settings → Users → Profile → Delegate, table user_delegates) for
 * that delegate instead — a backup covering while they're away, so
 * a tagged/eligible approver being unavailable doesn't stall the
 * workflow the same way an unassigned role used to. Replaces rather
 * than adds: for an 'all'-rule step this keeps the required
 * signoff count the same (the delegate acts IN PLACE of the
 * original, not as an extra required approval); for an 'any'-rule
 * step it makes no practical difference either way.
 *
 * Re-applies the requester exclusion after substitution — a
 * delegate could themselves happen to be the gatepass's own
 * requester, which the earlier SQL-level exclusion can't catch
 * since it only knows about the original (pre-substitution) users.
 */
private function substituteDelegates(array $users, int $requesterId): array
{
    if (!$users) {
        return $users;
    }

    $ids = array_map(static fn ($u) => (int) $u['id'], $users);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $this->db->prepare("
        SELECT ud.user_id, ud.delegate_user_id
        FROM user_delegates ud
        INNER JOIN users u ON u.id = ud.delegate_user_id AND u.is_active = 1
        WHERE ud.user_id IN ({$placeholders})
          AND ud.starts_at <= NOW()
          AND ud.ends_at   >= NOW()
    ");
    $stmt->execute($ids);
    $delegateMap = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $delegateMap[(int) $row['user_id']] = (int) $row['delegate_user_id'];
    }

    if (!$delegateMap) {
        return $users;
    }

    $resolvedIds = [];
    foreach ($ids as $id) {
        $resolvedIds[] = $delegateMap[$id] ?? $id;
    }

    $resolvedIds = array_values(array_unique(array_filter(
        $resolvedIds,
        static fn ($id) => $id !== $requesterId
    )));

    return array_map(static fn ($id) => ['id' => $id], $resolvedIds);
}

    // ── START WORKFLOW ───────────────────────────────────────

    public function startWorkflow(int $gatepassId, int $workflowId): void
    {
        if ($this->hasActiveWorkflow($gatepassId)) {
            throw new \Exception("Workflow already started.");
        }

        // FIX: INSERT has 5 columns; VALUES had 3 placeholders + 2 literals = 5 total → correct
        $stmt = $this->db->prepare("
            INSERT INTO gatepass_workflow_instances
                (gatepass_id, workflow_id, current_step_order, status, started_at)
            VALUES (?, ?, 1, 'in_progress', NOW())
        ");
        $stmt->execute([$gatepassId, $workflowId]);

        $instanceId = (int) $this->db->lastInsertId();
        $this->createApprovalsForStep($instanceId, 1);
    }

    // ── HELPERS ──────────────────────────────────────────────

    public function hasActiveWorkflow(int $gatepassId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM gatepass_workflow_instances
            WHERE gatepass_id = ? AND status = 'in_progress'
        ");
        $stmt->execute([$gatepassId]);
        return (bool) $stmt->fetchColumn();
    }

    // FIX: removed unused int $tenantId first parameter (was called with 0)
    public function getPendingForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT
                ga.id AS id,
                gwi.id AS workflow_instance_id,
                g.id   AS gatepass_id,
                g.gatepass_number,
                g.purpose,
                ws.name AS step_name,
                gwi.status AS workflow_status,
                ga.status  AS approval_status,
                CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name,
                gwi.started_at AS created_at
            FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi ON gwi.id = ga.workflow_instance_id
            INNER JOIN gatepasses g                    ON g.id   = gwi.gatepass_id
            INNER JOIN workflow_steps ws               ON ws.id  = ga.workflow_step_id
            INNER JOIN user_roles ur                   ON ur.role_id = ws.role_id AND ur.user_id = :user_id
            INNER JOIN users u                         ON u.id   = g.created_by
            WHERE ga.status = 'pending'
            ORDER BY gwi.started_at DESC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // FIX: removed unused int $tenantId first parameter (was called with 0)
    public function findApproval(int $approvalId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                ga.id AS id,
                ga.status AS approval_status,
                ga.acted_at,
                gwi.status AS workflow_status,
                gwi.started_at AS created_at,
                ws.name AS step_name,
                g.id AS gatepass_id,
                g.gatepass_number,
                g.purpose,
                CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name
            FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi ON gwi.id = ga.workflow_instance_id
            INNER JOIN gatepasses g                    ON g.id   = gwi.gatepass_id
            INNER JOIN workflow_steps ws               ON ws.id  = ga.workflow_step_id
            INNER JOIN users u                         ON u.id   = g.created_by
            WHERE ga.id = :approval_id AND ga.approver_user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([':approval_id' => $approvalId, ':user_id' => $userId]);
        $approval = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$approval) {
            return null;
        }

        $itemsStmt = $this->db->prepare("SELECT * FROM gatepass_items WHERE gatepass_id = :gatepass_id");
        $itemsStmt->execute([':gatepass_id' => $approval['gatepass_id']]);
        $approval['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        return $approval;
    }

    public function findUserApproval(int $approvalId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ga.*, g.gatepass_number, g.purpose
            FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi ON gwi.id = ga.workflow_instance_id
            INNER JOIN gatepasses g ON g.id = gwi.gatepass_id
            WHERE ga.id = ? AND ga.approver_user_id = ? AND ga.status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$approvalId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
