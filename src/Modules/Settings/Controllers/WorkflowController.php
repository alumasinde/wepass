<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use PDO;
use Throwable;

class WorkflowController extends Controller
{
    private PDO $db;

    public function __construct()
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }

        $this->db = DB::connect();
    }

    /**
     * Gates the new step-configuration/approver-assignment endpoints
     * behind the same 'settings.update' permission every other
     * admin-only settings screen uses. Deliberately not applied to
     * the pre-existing workflow endpoints above/below (those were
     * auth-only before this change and are left as-is here) — this
     * only covers the new surface being added.
     */
    private function requireSettingsPermission(): void
    {
        $permission = new Permission(DB::connect());
        if (!$permission->can('settings.update')) {
            Response::abort(403, "You don't have permission to manage workflow steps.");
        }
    }

    private function findOrFail(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM workflows WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::abort(404, 'Workflow not found.');
        }

        return $row;
    }

    // ───────────────── INDEX ─────────────────

    public function index()
    {
        $stmt = $this->db->query("
            SELECT w.*,
                   (SELECT COUNT(*) FROM workflow_steps ws WHERE ws.workflow_id = w.id) AS step_count
            FROM workflows w
            ORDER BY w.created_at DESC
        ");

        return $this->view('Settings::Workflows/index', [
            'title' => 'Workflows',
            'workflows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    // ───────────────── CREATE ─────────────────

    public function create()
    {
        return $this->view('Settings::Workflows/create', [
            'title' => 'Create Workflow'
        ]);
    }

   public function store(Request $request)
{
    try {
        // دعم JSON + form-data
        $raw  = file_get_contents('php://input');
        $json = json_decode($raw, true);

        $name = trim($json['name'] ?? $request->input('name') ?? '');
        $description = trim($json['description'] ?? $request->input('description') ?? '');

        if ($name === '') {
            return Response::json([
                'message' => 'Workflow name is required.'
            ], 422);
        }

        $stmt = $this->db->prepare("
            INSERT INTO workflows (name, description, is_active, created_at)
            VALUES (:name, :description, 1, NOW())
        ");

        $stmt->execute([
            ':name' => $name,
            ':description' => $description
        ]);

        return Response::json([
            'success' => true,
            'id' => (int) $this->db->lastInsertId()
        ]);

    } catch (\PDOException $e) {

        error_log("Workflow DB Error: " . $e->getMessage());

        return Response::json([
            'message' => 'Database error'
        ], 500);

    } catch (Throwable $e) {

        error_log("Workflow Fatal Error: " . $e->getMessage());

        return Response::json([
            'message' => 'Internal server error'
        ], 500);
    }
}

    // ───────────────── EDIT ─────────────────

    public function edit(Request $request, int $id)
    {
        return $this->view('Settings::Workflows/edit', [
            'title' => 'Edit Workflow',
            'workflow' => $this->findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $this->findOrFail($id);

        $name = trim($request->input('name') ?? '');
        $desc = trim($request->input('description') ?? '');
        $active = $request->input('is_active') ? 1 : 0;

        if ($name === '') {
            Response::abort(422, 'Workflow name is required.');
        }

        $stmt = $this->db->prepare("
            UPDATE workflows
            SET name = ?, description = ?, is_active = ?
            WHERE id = ?
        ");

        $stmt->execute([$name, $desc, $active, $id]);

        return $this->redirect('/settings/workflows');
    }

    // ───────────────── STEPS ─────────────────

    public function steps(Request $request, int $id)
    {
        $workflow = $this->findOrFail($id);

        $stmt = $this->db->prepare("
            SELECT ws.*, r.name AS role_name,
                   (SELECT COUNT(*) FROM workflow_step_approvers wsa WHERE wsa.workflow_step_id = ws.id) AS approver_count
            FROM workflow_steps ws
            JOIN roles r ON r.id = ws.role_id
            WHERE ws.workflow_id = ?
            ORDER BY ws.step_order ASC
        ");
        $stmt->execute([$id]);

        $roles = $this->db->query("
            SELECT id, name FROM roles ORDER BY name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $departments = $this->db->query("
            SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        return $this->view('Settings::Workflows/steps', [
            'title' => 'Workflow Steps',
            'workflow' => $workflow,
            'steps' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'roles' => $roles,
            'departments' => $departments,
        ]);
    }

   public function storeStep(Request $request, int $id)
{
    $this->findOrFail($id);

    $order = (int)$request->input('step_order');
    $name  = trim($request->input('step_name') ?? '');
    $role  = (int)$request->input('role_id');

    if ($order <= 0 || $name === '' || $role <= 0) {
        Response::abort(422, 'All fields are required.');
    }

    // validate role
    $stmt = $this->db->prepare("SELECT id FROM roles WHERE id = ?");
    $stmt->execute([$role]);

    if (!$stmt->fetch()) {
        Response::abort(422, 'Invalid role.');
    }

    // enforce unique order (DO THIS BEFORE TRANSACTION)
    $check = $this->db->prepare("
        SELECT id FROM workflow_steps
        WHERE workflow_id = ? AND step_order = ?
    ");
    $check->execute([$id, $order]);

    if ($check->fetch()) {
        Response::abort(422, 'Step order already exists.');
    }

    [$assignmentType, $departmentScope, $departmentId, $approvalRule] = $this->parseStepRoutingInput($request);

    try {
        $this->db->beginTransaction();

        $stmt = $this->db->prepare("
            INSERT INTO workflow_steps
                (workflow_id, step_order, name, role_id, department_id, assignment_type, department_scope, approval_rule)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id, $order, $name, $role, $departmentId, $assignmentType, $departmentScope, $approvalRule]);

        $this->db->commit();

    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        error_log($e->getMessage());
        Response::abort(500, 'Failed to create step.');
    }

    return $this->redirect("/settings/workflows/{$id}/steps");
}

    /**
     * Validates and normalizes the routing fields shared by
     * create/update: assignment_type, department_scope,
     * department_id (only meaningful when scope is 'fixed'), and
     * approval_rule. Aborts with 422 on anything invalid.
     */
    private function parseStepRoutingInput(Request $request): array
    {
        $assignmentType = $request->input('assignment_type', 'role_department');
        if (!in_array($assignmentType, ['role_department', 'explicit'], true)) {
            Response::abort(422, 'Invalid assignment type.');
        }

        $departmentScope = $request->input('department_scope', 'same_as_request');
        if (!in_array($departmentScope, ['same_as_request', 'fixed', 'any'], true)) {
            Response::abort(422, 'Invalid department scope.');
        }

        $approvalRule = $request->input('approval_rule', 'all');
        if (!in_array($approvalRule, ['all', 'any'], true)) {
            Response::abort(422, 'Invalid approval rule.');
        }

        $departmentId = null;
        if ($assignmentType === 'role_department' && $departmentScope === 'fixed') {
            $departmentId = (int) $request->input('department_id', 0);
            if ($departmentId <= 0) {
                Response::abort(422, 'A department is required when the scope is "fixed department".');
            }

            $stmt = $this->db->prepare("SELECT id FROM departments WHERE id = ?");
            $stmt->execute([$departmentId]);
            if (!$stmt->fetch()) {
                Response::abort(422, 'Invalid department.');
            }
        }

        return [$assignmentType, $departmentScope, $departmentId, $approvalRule];
    }

    private function findStepOrFail(int $workflowId, int $stepId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM workflow_steps WHERE id = ? AND workflow_id = ? LIMIT 1");
        $stmt->execute([$stepId, $workflowId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::abort(404, 'Workflow step not found.');
        }

        return $row;
    }

    // ───────────────── EDIT STEP ─────────────────

    public function editStep(Request $request, int $id, int $stepId)
    {
        $this->requireSettingsPermission();

        $workflow = $this->findOrFail($id);
        $step     = $this->findStepOrFail($id, $stepId);

        $roles = $this->db->query("SELECT id, name FROM roles ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $departments = $this->db->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        return $this->view('Settings::Workflows/step_edit', [
            'title'       => 'Edit Step',
            'workflow'    => $workflow,
            'step'        => $step,
            'roles'       => $roles,
            'departments' => $departments,
        ]);
    }

    public function updateStep(Request $request, int $id, int $stepId)
    {
        $this->requireSettingsPermission();

        $this->findOrFail($id);
        $this->findStepOrFail($id, $stepId);

        $order = (int) $request->input('step_order');
        $name  = trim($request->input('step_name') ?? '');
        $role  = (int) $request->input('role_id');

        if ($order <= 0 || $name === '' || $role <= 0) {
            Response::abort(422, 'All fields are required.');
        }

        $stmt = $this->db->prepare("SELECT id FROM roles WHERE id = ?");
        $stmt->execute([$role]);
        if (!$stmt->fetch()) {
            Response::abort(422, 'Invalid role.');
        }

        $check = $this->db->prepare("
            SELECT id FROM workflow_steps WHERE workflow_id = ? AND step_order = ? AND id != ?
        ");
        $check->execute([$id, $order, $stepId]);
        if ($check->fetch()) {
            Response::abort(422, 'Step order already exists.');
        }

        [$assignmentType, $departmentScope, $departmentId, $approvalRule] = $this->parseStepRoutingInput($request);

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE workflow_steps
                SET step_order = ?, name = ?, role_id = ?, department_id = ?,
                    assignment_type = ?, department_scope = ?, approval_rule = ?
                WHERE id = ? AND workflow_id = ?
            ");
            $stmt->execute([$order, $name, $role, $departmentId, $assignmentType, $departmentScope, $approvalRule, $stepId, $id]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log($e->getMessage());
            Response::abort(500, 'Failed to update step.');
        }

        return $this->redirect("/settings/workflows/{$id}/steps");
    }

    // ───────────────── ASSIGN APPROVERS (explicit steps) ─────────────────

    public function stepApprovers(Request $request, int $id, int $stepId)
    {
        $this->requireSettingsPermission();

        $workflow = $this->findOrFail($id);
        $step     = $this->findStepOrFail($id, $stepId);

        $users = $this->db->query("
            SELECT u.id, u.first_name, u.last_name, u.email, d.name AS department_name,
                   GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') AS role_names
            FROM users u
            LEFT JOIN departments d  ON d.id = u.department_id
            LEFT JOIN user_roles ur  ON ur.user_id = u.id
            LEFT JOIN roles r        ON r.id = ur.role_id
            WHERE u.is_active = 1
            GROUP BY u.id
            ORDER BY u.first_name ASC, u.last_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("SELECT user_id FROM workflow_step_approvers WHERE workflow_step_id = ?");
        $stmt->execute([$stepId]);
        $assignedUserIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return $this->view('Settings::Workflows/step_approvers', [
            'title'           => 'Assign Approvers',
            'workflow'        => $workflow,
            'step'            => $step,
            'users'           => $users,
            'assignedUserIds' => $assignedUserIds,
        ]);
    }

    public function storeStepApprovers(Request $request, int $id, int $stepId)
    {
        $this->requireSettingsPermission();

        $this->findOrFail($id);
        $this->findStepOrFail($id, $stepId);

        $userIds = $request->input('user_ids', []);
        if (!is_array($userIds)) {
            Response::abort(400, 'Invalid input.');
        }
        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            fn ($v) => $v > 0
        )));

        try {
            $this->db->beginTransaction();

            $this->db->prepare("DELETE FROM workflow_step_approvers WHERE workflow_step_id = ?")
                      ->execute([$stepId]);

            if ($userIds) {
                // Re-check against the DB rather than trusting the
                // posted ids outright — guards against a stale or
                // forged id (e.g. a since-deactivated user) slipping
                // through as an eligible approver.
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $stmt = $this->db->prepare("
                    SELECT id FROM users WHERE is_active = 1 AND id IN ({$placeholders})
                ");
                $stmt->execute($userIds);
                $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

                $insert = $this->db->prepare("
                    INSERT INTO workflow_step_approvers (workflow_step_id, user_id) VALUES (?, ?)
                ");
                foreach ($validIds as $uid) {
                    $insert->execute([$stepId, $uid]);
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log($e->getMessage());
            Response::abort(500, 'Failed to save approvers.');
        }

        return $this->redirect("/settings/workflows/{$id}/steps");
    }

    // ───────────────── ASSIGN (FIXED MODEL) ─────────────────

    public function assign(Request $request, int $id)
    {
        $workflow = $this->findOrFail($id);

        $types = $this->db->query("
            SELECT id, name, workflow_id
            FROM gatepass_types
            ORDER BY name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        return $this->view('Settings::Workflows/assign', [
            'title' => 'Assign Workflow',
            'workflow' => $workflow,
            'gatepassTypes' => $types,
        ]);
    }

    public function storeAssignment(Request $request, int $id)
    {
        $this->findOrFail($id);

        $typeId = (int)$request->input('gatepass_type_id');

        if ($typeId <= 0) {
            Response::abort(422, 'Gatepass type required.');
        }

        $stmt = $this->db->prepare("
            UPDATE gatepass_types
            SET workflow_id = ?
            WHERE id = ?
        ");

        $stmt->execute([$id, $typeId]);

        return $this->redirect('/settings/workflows');
    }

    // ───────────────── API (CRITICAL) ─────────────────

    public function list()
    {
        $stmt = $this->db->query("
            SELECT id, name
            FROM workflows
            WHERE is_active = 1
            ORDER BY name ASC
        ");

        return Response::json([
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }
}