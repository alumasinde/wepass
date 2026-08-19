<?php

namespace App\Modules\Gatepass\Repositories;

use App\Core\DB;
use PDO;

class GatepassItemRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function insertMany(int $gatepassId, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO gatepass_items
                (gatepass_id, item_name, description, quantity, serial_number, is_returnable)
            VALUES
                (:gatepass_id, :item_name, :description, :quantity, :serial_number, :is_returnable)
        ");

        foreach ($items as $item) {
            $name = trim((string) ($item['item_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $stmt->execute([
                ':gatepass_id'   => $gatepassId,
                ':item_name'     => $name,
                ':description'   => $item['description']   ?? null,
                ':quantity'      => max(1, (int) ($item['quantity'] ?? 1)),
                ':serial_number' => $item['serial_number'] ?? null,
                ':is_returnable' => (int) ($item['is_returnable'] ?? 0),
            ]);
        }
    }

    public function findByGatepass(int $gatepassId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM gatepass_items WHERE gatepass_id = :gatepass_id ORDER BY id ASC
        ");
        $stmt->execute([':gatepass_id' => $gatepassId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteByGatepass(int $gatepassId): void
    {
        $this->db->prepare("DELETE FROM gatepass_items WHERE gatepass_id = :gatepass_id")
                 ->execute([':gatepass_id' => $gatepassId]);
    }

    public function updateReturnedQuantity(int $itemId, int $returnedQty): bool
    {
        $stmt = $this->db->prepare("
            UPDATE gatepass_items SET returned_quantity = :qty WHERE id = :id
        ");
        $stmt->execute([':qty' => max(0, $returnedQty), ':id' => $itemId]);
        return $stmt->rowCount() > 0;
    }
}
