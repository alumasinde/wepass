<?php

namespace App\Modules\Gatepass\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Gatepass\DTOs\GatepassDTO;
use App\Modules\Gatepass\Repositories\GatepassItemRepository;
use App\Modules\Gatepass\Repositories\GatepassRepository;
use App\Modules\Gatepass\Repositories\GatepassStatusRepository;
use App\Modules\Settings\Services\TenantSettingService;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * GatepassService — per-database isolation model.
 * No tenant_id arguments needed; all queries operate on the tenant DB.
 */
class GatepassService
{
    private PDO $db;
    private GatepassRepository       $repo;
    private GatepassItemRepository   $itemRepo;
    private GatepassStatusRepository $statusRepo;
    private ApprovalService          $approvalService;
    private QRService                 $qrService;
    private TenantSettingService      $settings;

    /** @var array|null per-instance cache — fetched at most once per request */
    private ?array $workflowRules = null;

    public function __construct()
    {
        $this->db              = DB::connect();
        $this->repo            = new GatepassRepository();
        $this->itemRepo        = new GatepassItemRepository();
        $this->statusRepo      = new GatepassStatusRepository();
        $this->approvalService = new ApprovalService();
        $this->qrService       = new QRService();
        $this->settings        = new TenantSettingService();
    }

    /**
     * Settings -> Gatepass Rules — which statuses allow check-in/
     * check-out, and whether check-in requires the gatepass to be
     * marked returnable. Cached per-instance so a list of 50
     * gatepasses (50 getAvailableActions() calls) only fetches this
     * once, not 50 times.
     */
    private function getWorkflowRules(): array
    {
        if ($this->workflowRules === null) {
            $this->workflowRules = $this->settings->get(
                'gatepass_workflow_rules',
                GatepassWorkflow::DEFAULT_RULES
            );
        }
        return $this->workflowRules;
    }

    // ── CREATE ───────────────────────────────────────────────

    public function create(GatepassDTO $dto): int
    {
        try {
            $this->db->beginTransaction();

            if (!$dto->gatepass_type_id) {
                throw new InvalidArgumentException('Gatepass type is required.');
            }

            // Authoritative — NOT whatever (if anything) the client
            // sent for this. Previously this came straight from the
            // create form's "Needs Approval" checkbox with no
            // permission check and no server-side override, which
            // meant any user creating their own gatepass could
            // untick it and self-approve on the spot, skipping the
            // workflow entirely. It's now controlled only on the
            // gatepass TYPE itself (Settings → Gatepass Types).
            $dto->needs_approval = $this->repo->typeRequiresApproval($dto->gatepass_type_id);

            $gatepassNumber = $this->generateGatepassNumber();
            $statusId       = $this->resolveInitialStatus($dto);
            $departmentId   = $this->getUserDepartment($dto->created_by);

            $gatepassId = $this->repo->create([
                'visit_id'             => $dto->visit_id,
                'gatepass_type_id'     => $dto->gatepass_type_id,
                'gatepass_number'      => $gatepassNumber,
                'status_id'            => $statusId,
                'purpose'              => $dto->purpose,
                'is_returnable'        => (int) $dto->is_returnable,
                'expected_return_date' => $dto->expected_return_date,
                'needs_approval'       => (int) $dto->needs_approval,
                'created_by'           => $dto->created_by,
                'department_id'        => $departmentId,
            ]);

            $this->itemRepo->insertMany($gatepassId, $dto->items);

            if ($dto->needs_approval) {
                $workflowId = $this->repo->getWorkflowIdFromType($dto->gatepass_type_id);

                if (!$workflowId) {
                    throw new RuntimeException('No workflow configured for this gatepass type.');
                }

                $this->approvalService->startWorkflow($gatepassId, $workflowId);
            }

            Audit::log('gatepass.created', 'gatepass', $gatepassId, [
                'gatepass_number' => $gatepassNumber,
                'needs_approval'  => $dto->needs_approval,
            ]);

            $this->db->commit();

            // Outside the transaction on purpose: QR generation calls
            // an external service and must never hold a DB lock open
            // while it does. A failure here is logged by QRService
            // and does not roll back or fail the gatepass creation —
            // the gatepass number itself remains valid without an
            // image, and can be regenerated later.
            $qrPath = $this->qrService->getOrCreate($gatepassNumber);
            if ($qrPath !== null) {
                $this->repo->updateQrPath($gatepassId, $qrPath);
            }

            return $gatepassId;

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── UPDATE ───────────────────────────────────────────────

    public function update(int $id, GatepassDTO $dto): bool
    {
        try {
            $this->db->beginTransaction();

            if ($dto->gatepass_type_id) {
                // Same authoritative override as create() — the edit
                // form can change the gatepass type too, so re-derive
                // from whichever type ends up selected rather than
                // trusting the posted needs_approval value.
                $dto->needs_approval = $this->repo->typeRequiresApproval($dto->gatepass_type_id);
            }

            $updated = $this->repo->update($id, [
                'visit_id'             => $dto->visit_id,
                'gatepass_type_id'     => $dto->gatepass_type_id,
                'purpose'              => $dto->purpose,
                'is_returnable'        => (int) $dto->is_returnable,
                'expected_return_date' => $dto->expected_return_date,
                'needs_approval'       => (int) $dto->needs_approval,
            ]);

            if (!$updated) {
                throw new InvalidArgumentException('Gatepass update failed or no changes made.');
            }

            $this->itemRepo->deleteByGatepass($id);
            $this->itemRepo->insertMany($id, $dto->items);

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── DELETE ───────────────────────────────────────────────

    public function delete(int $id): bool
    {
        try {
            $this->db->beginTransaction();

            // NOTE: this used to also hard-delete every gatepass_item
            // for this record before soft-deleting the gatepass
            // itself — which meant item detail (serials, quantities,
            // descriptions) was permanently destroyed even though the
            // parent record now survives as a soft-deleted row. Items
            // stay put; they simply become unreachable through the
            // normal app flow the moment their parent gatepass is
            // soft-deleted (GatepassRepository::findById/findByNumber
            // both filter out soft-deleted gatepasses first).
            if (!$this->repo->delete($id)) {
                throw new InvalidArgumentException('Gatepass not found or already deleted.');
            }

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── CHECK IN / OUT ───────────────────────────────────────

    public function checkIn(int $gatepassId, int $userId): bool
    {
        $gatepass = $this->repo->findById($gatepassId);

        if (!$gatepass) {
            throw new RuntimeException('Gatepass not found.');
        }

        if (!empty($gatepass['actual_in'])) {
            throw new RuntimeException('Gatepass already checked in.');
        }

        // Server-side enforcement of the same rule the button
        // visibility uses — a type with check-in disabled must
        // reject this even if someone posts to the endpoint directly,
        // not just hide the button.
        $allowed = $this->decodeAllowedActions($gatepass['allowed_actions'] ?? null);
        if (!$allowed['checkin']) {
            throw new RuntimeException('This gatepass type does not allow check-in.');
        }

        $eligibility = GatepassWorkflow::eligibility($gatepass, $this->getWorkflowRules());

        if (!$eligibility['checkin_eligible']) {
            throw new RuntimeException('Check-in not allowed in current state.');
        }

        $now              = date('Y-m-d H:i:s');
        $checkedInId      = $this->statusRepo->requireIdByCode('CHECKED_IN');

        // The exact status this gatepass was in when we read it a
        // moment ago — the repository confirms it's STILL in this
        // exact status before writing, which is the real concurrency
        // check. Which statuses were acceptable in the first place
        // was already decided by the eligibility check above (now
        // configurable via Settings -> Gatepass Rules) — the
        // repository no longer re-decides that independently.
        $success = $this->repo->checkIn($gatepassId, $userId, $now, $checkedInId, (int) $gatepass['status_id']);

        if (!$success) {
            throw new RuntimeException('Check-in failed. Gatepass may have been modified concurrently.');
        }

        Audit::log('gatepass.checked_in', 'gatepass', $gatepassId, ['user_id' => $userId, 'timestamp' => $now]);
        return true;
    }

    public function checkOut(int $gatepassId, int $userId): bool
    {
        $gatepass = $this->repo->findById($gatepassId);

        if (!$gatepass) {
            throw new RuntimeException('Gatepass not found.');
        }

        if (!empty($gatepass['actual_out'])) {
            throw new RuntimeException('Gatepass already checked out.');
        }

        $allowed = $this->decodeAllowedActions($gatepass['allowed_actions'] ?? null);
        if (!$allowed['checkout']) {
            throw new RuntimeException('This gatepass type does not allow check-out.');
        }

        $eligibility = GatepassWorkflow::eligibility($gatepass, $this->getWorkflowRules());

        if (!$eligibility['checkout_eligible']) {
            throw new RuntimeException('Checkout not allowed in current state.');
        }

        $timestamp      = date('Y-m-d H:i:s');
        $checkedOutId   = $this->statusRepo->requireIdByCode('CHECKED_OUT');

        $success = $this->repo->checkOut($gatepassId, $userId, $timestamp, $checkedOutId, (int) $gatepass['status_id']);

        if (!$success) {
            throw new RuntimeException('Checkout failed. Gatepass may have been modified concurrently.');
        }

        Audit::log('gatepass.checked_out', 'gatepass', $gatepassId, ['user_id' => $userId, 'timestamp' => $timestamp]);
        return true;
    }

    public function markReturned(int $gatepassId): bool
    {
        $gatepass = $this->repo->findById($gatepassId);

        if (!$gatepass || !$gatepass['is_returnable']) {
            throw new RuntimeException($gatepass ? 'Gatepass is not returnable.' : 'Gatepass not found.');
        }

        $statusId = $this->statusRepo->requireIdByCode('RETURNED');
        return $this->repo->updateStatus($gatepassId, $statusId);
    }

    // ── FIND ─────────────────────────────────────────────────

    public function find(int $id): ?array
    {
        $gatepass = $this->repo->findById($id);
        if (!$gatepass) {
            return null;
        }
        $gatepass['items'] = $this->itemRepo->findByGatepass($id);
        return $gatepass;
    }

    public function findByNumber(string $number): ?array
    {
        $number   = trim($number);
        $gatepass = $number ? $this->repo->findByNumber($number) : null;
        if (!$gatepass) {
            return null;
        }
        $gatepass['items'] = $this->itemRepo->findByGatepass((int) $gatepass['id']);
        return $gatepass;
    }

    /**
     * $canViewAll comes from GatepassPolicy::canViewAll() — the
     * 'gatepass.view_all' DB permission — not a hardcoded role name.
     * Previously this checked in_array($role, ['admin', 'General
     * Manager', 'superadmin']), which silently broke if a role was
     * ever renamed and bypassed the DB-driven permission model
     * entirely.
     */
    public function list(int $userId, bool $canViewAll): array
    {
        if ($canViewAll) {
            return $this->repo->findAll();
        }

        $departmentId = $this->getUserDepartment($userId);
        return $this->repo->findAllByDepartment($departmentId);
    }

    public function getAvailableActions(array $gatepass): array
    {
        $eligibility = GatepassWorkflow::eligibility($gatepass, $this->getWorkflowRules());

        // FIX: previously only checked gatepass STATUS — completely
        // ignored what the gatepass TYPE actually allows. A type with
        // checkout unchecked in Settings -> Gatepass Types would still
        // show a working Check Out button on every gatepass of that
        // type, since this never looked at the type's own config at
        // all. Both now have to agree: the type must allow the action
        // AND the gatepass's current state must make it eligible.
        $allowed = $this->decodeAllowedActions($gatepass['allowed_actions'] ?? null);

        return [
            'can_checkin'  => $allowed['checkin']  && $eligibility['checkin_eligible'],
            'can_checkout' => $allowed['checkout'] && $eligibility['checkout_eligible'],
        ];
    }

    /**
     * gatepass_types.allowed_actions is stored as JSON
     * ({"checkin":true,"checkout":true}) — decode defensively so a
     * null/malformed value can never make an action look allowed
     * when it isn't (defaults both to false, not true, on any
     * decode failure).
     */
    private function decodeAllowedActions(mixed $raw): array
    {
        $defaults = ['checkin' => false, 'checkout' => false];

        if (!is_string($raw) || $raw === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        return [
            'checkin'  => !empty($decoded['checkin']),
            'checkout' => !empty($decoded['checkout']),
        ];
    }

    public function getVisitsForUser(?int $departmentId): array
    {
        $sql = "
            SELECT
                v.id,
                v.purpose,
                v.expected_in,
                v.expected_out,
                v.checkin_time,
                vs.name AS status_name,
                CONCAT(vis.first_name, ' ', vis.last_name) AS visitor_name,
                vis.phone,
                vis.id_number
            FROM visits v
            INNER JOIN visitors      vis ON vis.id = v.visitor_id
            INNER JOIN visit_statuses vs ON vs.id  = v.visit_status_id
            WHERE vs.code           = 'CHECKED_IN'
              AND v.checkout_time IS NULL
        ";

        $params = [];

        if ($departmentId !== null) {
            $sql .= ' AND v.department_id = :department_id';
            $params[':department_id'] = $departmentId;
        }

        $sql .= ' ORDER BY v.checkin_time DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PRIVATE HELPERS ──────────────────────────────────────

    private function resolveInitialStatus(GatepassDTO $dto): int
    {
        $code = $dto->needs_approval ? 'PENDING' : 'APPROVED';
        return $this->statusRepo->requireIdByCode($code);
    }

    private function getUserDepartment(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT department_id FROM users WHERE id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !(int) $row['department_id']) {
            throw new RuntimeException('User department not configured.');
        }

        return (int) $row['department_id'];
    }

    private function generateGatepassNumber(): string
    {
        $stmt = $this->db->prepare("
            SELECT config_json
            FROM   tenant_settings
            WHERE  setting_key = 'gatepass_numbering'
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Fall back to config.ini values
            $prefix  = config('gatepass.prefix',   'GP');
            $padding = (int) config('gatepass.zero_pad', 5);
            $seq     = 1;
        } else {
            $config = json_decode($row['config_json'], true) ?? [];
            $config = array_merge([
                'prefix'        => config('gatepass.prefix', 'GP'),
                'include_year'  => true,
                'include_month' => false,
                'padding'       => (int) config('gatepass.zero_pad', 5),
                'reset_yearly'  => true,
                'current_year'  => (int) date('Y'),
                'sequence'      => 1,
            ], $config);

            $year = date('Y');

            if ($config['reset_yearly'] && (string) $config['current_year'] !== $year) {
                $config['sequence']     = 1;
                $config['current_year'] = (int) $year;
            }

            $parts = [];

            if (!empty($config['prefix'])) {
                $parts[] = $config['prefix'];
            }
            if (!empty($config['include_year'])) {
                $parts[] = $year;
            }
            if (!empty($config['include_month'])) {
                $parts[] = date('m');
            }

            $parts[] = str_pad((string) $config['sequence'], (int) $config['padding'], '0', STR_PAD_LEFT);
            $config['sequence']++;

            $this->db->prepare("
                UPDATE tenant_settings
                SET    config_json = :cfg
                WHERE  setting_key = 'gatepass_numbering'
            ")->execute([':cfg' => json_encode($config)]);

            return implode('-', $parts);
        }

        // Minimal fallback (no tenant_settings table row)
        $prefix  = config('gatepass.prefix',   'GP');
        $padding = (int) config('gatepass.zero_pad', 5);
        $stmt2   = $this->db->query("SELECT COUNT(*) + 1 AS next FROM gatepasses");
        $seq     = (int) ($stmt2->fetchColumn() ?: 1);

        return $prefix . '-' . date('Y') . '-' . str_pad((string) $seq, $padding, '0', STR_PAD_LEFT);
    }
}
