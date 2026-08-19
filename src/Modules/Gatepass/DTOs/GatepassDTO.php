<?php

namespace App\Modules\Gatepass\DTOs;

/**
 * GatepassDTO — per-database isolation model.
 * No tenant_id property needed.
 */
class GatepassDTO
{
    public ?int    $visit_id;
    public ?int    $gatepass_type_id;
    public string  $purpose;
    public bool    $is_returnable;
    public ?string $expected_return_date;
    public bool    $needs_approval;
    public int     $created_by;
    public array   $items;

    public function __construct(
        int     $created_by,
        ?int    $visit_id            = null,
        ?int    $gatepass_type_id    = null,
        string  $purpose             = '',
        bool    $is_returnable       = false,
        ?string $expected_return_date = null,
        bool    $needs_approval      = false,
        array   $items               = []
    ) {
        if ($created_by <= 0) {
            throw new \InvalidArgumentException('created_by is required.');
        }

        $this->created_by           = $created_by;
        $this->visit_id             = $visit_id;
        $this->gatepass_type_id     = $gatepass_type_id;
        $this->purpose              = trim($purpose);
        $this->is_returnable        = $is_returnable;
        $this->expected_return_date = $expected_return_date ?: null;
        $this->needs_approval       = $needs_approval;
        $this->items                = $items;
    }

   public static function fromRequest(array $data, int $userId): self
{
    $items = [];

    foreach ($data['items'] ?? [] as $item) {
        $name = trim((string) ($item['item_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $items[] = [
            'item_name'     => $name,
            'description'   => $item['description']   ?? null,
            'quantity'      => max(1, (int) ($item['quantity'] ?? 1)),
            'serial_number' => $item['serial_number']  ?? null,
            'is_returnable' => !empty($item['is_returnable']),
        ];
    }

    return new self(
        created_by:           $userId,
        visit_id:             isset($data['visit_id']) && $data['visit_id'] !== '' ? (int) $data['visit_id'] : null,
        gatepass_type_id:     isset($data['gatepass_type_id']) && $data['gatepass_type_id'] !== '' ? (int) $data['gatepass_type_id'] : null,
        purpose:              $data['purpose'] ?? '',
        is_returnable:        !empty($data['is_returnable']),
        expected_return_date: $data['expected_return_date'] ?? null,
        // needs_approval is deliberately NOT read from $data — it
        // used to be, which let anyone creating a gatepass untick a
        // plain checkbox and self-approve, skipping the workflow
        // entirely. GatepassService overrides this immediately from
        // the selected type's own configuration before it's used for
        // anything; true here is just a safe placeholder default.
        needs_approval:       true,
        items:                $items,
    );
}
}
