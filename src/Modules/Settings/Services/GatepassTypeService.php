<?php

namespace App\Modules\Settings\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Modules\Gatepass\DTOs\GatepassDTO;
use App\Modules\Gatepass\Services\GatepassWorkflow;
use App\Modules\Settings\Repositories\GatepassTypeRepository;
use App\Modules\Settings\Validation\GatepassTypeValidator;
use RuntimeException;

class GatepassTypeService
{
    public function __construct(
        private GatepassTypeRepository $repo
    ) {}

    public function all(): array
    {
        return $this->repo->all();
    }

    public function find(int $id)
    {
        $type = $this->repo->find($id);

        if (!$type) {
            throw new RuntimeException('Gatepass type not found.');
        }

        return $type;
    }

    // ───────────────── CREATE ─────────────────

    public function create(
        string $name,
        ?string $code,
        bool $checkin,
        bool $checkout,
        ?int $workflowId,
        bool $requiresApproval = true,
        string $direction = 'outbound'
    ): int {
        GatepassTypeValidator::validateCreate($name, $code, $checkin, $checkout);

        $direction = $direction === 'inbound' ? 'inbound' : 'outbound';

        $actions = [
            'checkin'  => $checkin,
            'checkout' => $checkout,
        ];

        try {
            $id = DB::transaction(function () use ($name, $code, $actions, $workflowId, $requiresApproval, $direction) {

                if ($workflowId !== null && !$this->repo->workflowExists($workflowId)) {
                    throw new RuntimeException('Invalid workflow selected.');
                }

                return $this->repo->create(
                    $name,
                    $code,
                    $actions,
                    $workflowId,
                    $requiresApproval,
                    $direction
                );
            });

            Audit::log(
                action: 'gatepass_type.created',
                entityType: 'gatepass_type',
                entityId: $id,
                metadata: [
                    'name'              => $name,
                    'code'              => $code,
                    'allowedActions'    => $actions,
                    'workflow_id'       => $workflowId,
                    'requires_approval' => $requiresApproval,
                    'direction'         => $direction
                ]
            );

            return $id;

        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Gatepass type creation failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    // ───────────────── UPDATE ─────────────────

    public function update(
        int $id,
        string $name,
        ?string $code,
        bool $checkin,
        bool $checkout,
        ?int $workflowId,
        bool $isActive = true,
        bool $requiresApproval = true,
        string $direction = 'outbound'
    ): bool {
        GatepassTypeValidator::validateUpdate($id, $name, $code, $checkin, $checkout);

        $direction = $direction === 'inbound' ? 'inbound' : 'outbound';

        $type = $this->find($id); // reuse method (ensures exception consistency)

        $afterActions = [
            'checkin'  => $checkin,
            'checkout' => $checkout,
        ];

        $before = [
            'name'              => $type->name,
            'code'              => $type->code,
            'allowedActions'    => $type->allowedActions,
            'workflow_id'       => $type->workflowId,
            'is_active'         => $type->isActive,
            'requires_approval' => $type->requiresApproval,
            'direction'         => $type->direction
        ];

        $after = [
            'name'              => $name,
            'code'              => $code,
            'allowedActions'    => $afterActions,
            'workflow_id'       => $workflowId,
            'is_active'         => $isActive,
            'requires_approval' => $requiresApproval,
            'direction'         => $direction
        ];

        try {
            DB::transaction(function () use (
                $id, $name, $code, $afterActions, $workflowId, $isActive, $requiresApproval, $direction
            ) {
                if ($workflowId !== null && !$this->repo->workflowExists($workflowId)) {
                    throw new RuntimeException('Invalid workflow selected.');
                }

                $this->repo->update(
                    $id,
                    $name,
                    $code,
                    $afterActions,
                    $workflowId,
                    $isActive,
                    $requiresApproval,
                    $direction
                );
            });

            Audit::log(
                action: 'gatepass_type.updated',
                entityType: 'gatepass_type',
                entityId: $id,
                metadata: [
                    'before' => $before,
                    'after'  => $after
                ]
            );

            return true;

        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Gatepass type update failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    // ───────────────── UPDATE ACTIONS ─────────────────

    public function updateActions(int $id, bool $checkin, bool $checkout): void
    {
        GatepassTypeValidator::validateActions($checkin, $checkout);

        $type = $this->find($id);

        $before = $type->allowedActions;
        $after  = ['checkin' => $checkin, 'checkout' => $checkout];

        try {
            DB::transaction(function () use ($id, $after, $before) {
                $this->repo->updateActions($id, $after);

                Audit::log(
                    action: 'gatepass_type.updated_actions',
                    entityType: 'gatepass_type',
                    entityId: $id,
                    metadata: ['before' => $before, 'after' => $after]
                );
            });

        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Gatepass update failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    // ───────────────── RESOLVE ACTIONS ─────────────────

    /**
     * FIX: was calling GatepassWorkflow::eligibility() with neither
     * 'direction' in the row array nor the tenant's configured
     * Gatepass Rules — meaning if this were ever actually called, it
     * would silently treat every type as outbound-with-defaults,
     * ignoring both this feature and Settings -> Gatepass Rules
     * entirely. Currently unused anywhere in the app; fixed for
     * correctness rather than left broken for whenever it is.
     */
    public function resolveActions(GatepassDTO $gatepass, $type, array $workflowRules = []): array
    {
        $allowed = $type->allowedActions ?? ['checkin' => false, 'checkout' => false];

        $eligibility = GatepassWorkflow::eligibility([
            'status_code'   => $gatepass->statusCode,
            'actual_in'     => $gatepass->actualIn,
            'actual_out'    => $gatepass->actualOut,
            'is_returnable' => $gatepass->isReturnable,
            'direction'     => $type->direction ?? 'outbound',
        ], $workflowRules);

        return [
            'can_checkin'  => $allowed['checkin']  && $eligibility['checkin_eligible'],
            'can_checkout' => $allowed['checkout'] && $eligibility['checkout_eligible'],
        ];
    }
}
