<?php

namespace App\Modules\Settings\DTOs;

class GatepassTypeDTO
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $name,
        public array $allowedActions = [],
        public ?string $code = null,
        public ?int $workflowId = null,
        public ?string $workflowName = null,
        public bool $isActive = true,
        public bool $requiresApproval = true,
        public string $direction = 'outbound',

    ) {
        $this->allowedActions = [
            'checkin' => !empty($this->allowedActions['checkin']),
            'checkout' => !empty($this->allowedActions['checkout']),
        ];

        // Never trust an unexpected value here — anything except the
        // two real directions silently falls back to 'outbound' (the
        // long-standing, safe default), rather than letting a typo'd
        // or tampered value reach the eligibility engine.
        if (!in_array($this->direction, ['outbound', 'inbound'], true)) {
            $this->direction = 'outbound';
        }
    }

    /**
     * Create DTO from DB row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            tenantId: (int)($row['tenant_id'] ?? 0),
            name: $row['name'],
            allowedActions: self::decodeActions($row['allowed_actions'] ?? null),
            code: $row['type_code'] ?? null,
            workflowId: isset($row['workflow_id']) ? (int)$row['workflow_id'] : null,
            workflowName: $row['workflow_name'] ?? null,
            isActive: (bool)$row['is_active'],
            requiresApproval: array_key_exists('requires_approval', $row) ? (bool)$row['requires_approval'] : true,
            direction: (string) ($row['direction'] ?? 'outbound'),
        );
    }

    /**
     * Convert to DB-ready array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type_code' => $this->code,
            'workflow_id' => $this->workflowId,
            'allowed_actions' => json_encode($this->allowedActions),
            'is_active' => $this->isActive ? 1 : 0,
            'requires_approval' => $this->requiresApproval ? 1 : 0,
            'direction' => $this->direction,
        ];
    }

    /**
     * Safe JSON decode
     */
    private static function decodeActions(?string $json): array
    {
        if (!$json) {
            return [];
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }
}
