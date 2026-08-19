<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\DB;
use App\Modules\Settings\Services\GatepassTypeService;
use App\Modules\Settings\Services\TenantSettingService;
use App\Modules\Gatepass\Services\GatepassWorkflow;
use RuntimeException;
use PDO;

class GatepassTypeController extends Controller
{
    private TenantSettingService $settings;

    public function __construct(
        private GatepassTypeService $service
    ) {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
        $this->settings = new TenantSettingService();
    }

    /**
     * The current tenant-wide Gatepass Rules (Settings -> Gatepass
     * Rules) — passed to the create/edit forms so they can show a
     * live summary of what a type's checkin/checkout checkboxes
     * actually mean in combination with these rules, instead of an
     * admin having to know both screens exist and mentally combine
     * them.
     */
    private function getWorkflowRules(): array
    {
        return $this->settings->get('gatepass_workflow_rules', GatepassWorkflow::DEFAULT_RULES);
    }

    // ───────────────── INDEX ─────────────────

    public function index()
    {
        return $this->view('Settings::gatepass-types/index', [
            'types' => $this->service->all(),
        ]);
    }

    // ───────────────── CREATE VIEW ─────────────────

    public function create()
    {
        return $this->view('Settings::gatepass-types/create', [
            'workflows'         => $this->getWorkflows(),
            'workflowRules'     => $this->getWorkflowRules(),
            'workflowStepsMap'  => $this->getWorkflowStepsMap(),
        ]);
    }

    // ───────────────── EDIT VIEW ─────────────────

    public function edit(Request $request, int $id)
    {
        try {
            return $this->view('Settings::gatepass-types/edit', [
                'type'              => $this->service->find($id),
                'workflows'         => $this->getWorkflows(),
                'workflowRules'     => $this->getWorkflowRules(),
                'workflowStepsMap'  => $this->getWorkflowStepsMap(),
            ]);

        } catch (RuntimeException $e) {
            // FIX 1: log the real reason before redirecting so it isn't lost silently
            error_log('[GatepassType] edit() failed for id=' . $id . ': ' . $e->getMessage());
            Response::redirect('/settings/gatepass-types');

        } catch (\Throwable $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());

            http_response_code(500);
            echo "Internal Server Error";
            exit;
        }
    }

    // ───────────────── STORE ─────────────────

    public function store(Request $request)
    {
        try {
            $body = $this->getPayload($request);

            $name = trim($body['name'] ?? '');
            $code = trim($body['code'] ?? '');

            if ($name === '') {
                return Response::json(['message' => 'Name is required'], 422);
            }

            $workflowId = $this->parseWorkflowId($body);
            $direction  = ($body['direction'] ?? 'outbound') === 'inbound' ? 'inbound' : 'outbound';

            $id = $this->service->create(
                $name,
                $code !== '' ? $code : null,
                $this->toBool($body['checkin'] ?? 0),
                $this->toBool($body['checkout'] ?? 0),
                $workflowId,
                $this->toBool($body['requires_approval'] ?? 1),
                $direction
            );

            return Response::json([
                'success' => true,
                'id'      => $id
            ], 201);

        } catch (RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 400);

        } catch (\Throwable $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());

            return Response::json(['message' => 'Internal server error'], 500);
        }
    }

    // ───────────────── UPDATE ─────────────────

    // FIX 2: accept $id from the route parameter (/gatepass-types/{id}/update)
    // instead of reading it from the request body (where it was never sent).
    public function update(Request $request, int $id)
    {
        try {
            if ($id <= 0) {
                return Response::json(['message' => 'Invalid ID'], 422);
            }

            $body = $this->getPayload($request);

            $name = trim($body['name'] ?? '');
            if ($name === '') {
                return Response::json(['message' => 'Name is required'], 422);
            }

            $code       = trim($body['code'] ?? '');
            $workflowId = $this->parseWorkflowId($body);
            $checkin    = $this->toBool($body['checkin'] ?? 0);
            $checkout   = $this->toBool($body['checkout'] ?? 0);
            $direction  = ($body['direction'] ?? 'outbound') === 'inbound' ? 'inbound' : 'outbound';

            $this->service->update(
                $id,
                $name,
                $code !== '' ? $code : null,
                $checkin,
                $checkout,
                $workflowId,
                $this->toBool($body['is_active'] ?? 1),
                $this->toBool($body['requires_approval'] ?? 1),
                $direction
            );

            return Response::json(['success' => true]);

        } catch (RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 400);

        } catch (\Throwable $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());

            return Response::json(['message' => 'Internal server error'], 500);
        }
    }

    // ───────────────── UPDATE ACTIONS ─────────────────

    public function updateActions(Request $request)
    {
        try {
            $body = $this->getPayload($request);

            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) {
                return Response::json(['message' => 'Invalid ID'], 422);
            }

            $this->service->updateActions(
                $id,
                $this->toBool($body['checkin'] ?? 0),
                $this->toBool($body['checkout'] ?? 0)
            );

            return Response::json(['success' => true]);

        } catch (RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 400);

        } catch (\Throwable $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());

            return Response::json(['message' => 'Internal server error'], 500);
        }
    }

    // ───────────────── HELPERS ─────────────────

    private function getWorkflows(): array
    {
        return DB::query("
            SELECT id, name
            FROM workflows
            WHERE is_active = 1
            ORDER BY name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Phase 1 of simplifying the Gatepass Types / Workflows / Gatepass
     * Rules confusion: previously the type edit screen only showed a
     * workflow's NAME in a dropdown — nothing about what it actually
     * does, meaning "what happens for this type" required leaving
     * this screen entirely and checking Settings -> Workflows
     * separately. This fetches every active workflow's actual steps
     * (same shape WorkflowController::steps() already uses) in one
     * go, keyed by workflow_id, so the live summary can show the real
     * approval chain the moment a workflow is selected — no
     * additional request needed as the dropdown changes.
     */
    private function getWorkflowStepsMap(): array
    {
        $steps = DB::query("
            SELECT ws.workflow_id, ws.step_order, ws.assignment_type,
                   ws.department_scope, ws.approval_rule, r.name AS role_name,
                   ws.id AS step_id
            FROM workflow_steps ws
            LEFT JOIN roles r ON r.id = ws.role_id
            ORDER BY ws.workflow_id ASC, ws.step_order ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($steps)) {
            return [];
        }

        // Explicit-assignment steps show actual approver names, not
        // just a count — pulled in one extra query rather than N+1,
        // since the number of workflows/steps per tenant is small.
        $explicitStepIds = array_column(
            array_filter($steps, fn ($s) => $s['assignment_type'] === 'explicit'),
            'step_id'
        );

        $approversByStep = [];
        if (!empty($explicitStepIds)) {
            $placeholders = implode(',', array_fill(0, count($explicitStepIds), '?'));
            $rows = DB::query("
                SELECT wsa.workflow_step_id, u.first_name, u.last_name
                FROM workflow_step_approvers wsa
                JOIN users u ON u.id = wsa.user_id
                WHERE wsa.workflow_step_id IN ({$placeholders})
                ORDER BY u.first_name ASC, u.last_name ASC
            ", $explicitStepIds)->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $approversByStep[$row['workflow_step_id']][] = trim($row['first_name'] . ' ' . $row['last_name']);
            }
        }

        $map = [];
        foreach ($steps as $step) {
            $map[(int) $step['workflow_id']][] = [
                'order'            => (int) $step['step_order'],
                'assignment_type'  => $step['assignment_type'],
                'department_scope' => $step['department_scope'],
                'approval_rule'    => $step['approval_rule'],
                'role_name'        => $step['role_name'],
                'approver_names'   => $approversByStep[$step['step_id']] ?? [],
            ];
        }

        return $map;
    }

    private function getPayload(Request $request): array
    {
        // FIX: was doing its own separate file_get_contents('php://input')
        // + json_decode() here, completely independent of Request's own
        // JSON-body handling (which CSRF middleware already reads from,
        // via input()/all()). php://input is not guaranteed re-readable
        // more than once across every PHP/SAPI setup — a second,
        // independent read after middleware already consumed it could
        // silently come back empty, discarding the whole submitted form
        // with no visible error. Request::all() already merges
        // $_GET/$_POST/decoded-JSON-body correctly and caches the JSON
        // parse, so every reader goes through one source of truth.
        return $request->all();
    }

    private function parseWorkflowId(array $body): ?int
    {
        if (!isset($body['workflow_id']) || $body['workflow_id'] === '') {
            return null;
        }

        return (int)$body['workflow_id'];
    }

    private function toBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}