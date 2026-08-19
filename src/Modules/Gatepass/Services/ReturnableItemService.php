<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use App\Core\Audit;
use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * Phase 4 returnable-item boundary.
 * Returns are append-only events and the aggregate returned quantity
 * can only move forward, never be overwritten backwards.
 */
final class ReturnableItemService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function recordReturn(
        int $itemId,
        int $quantity,
        ?int $actorUserId = null,
        ?string $reference = null,
        ?string $notes = null
    ): bool {
        if ($itemId < 1 || $quantity < 1) {
            throw new RuntimeException('A positive return quantity is required.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT id, gatepass_id, quantity, returned_quantity, is_returnable
                 FROM gatepass_items WHERE id = :id FOR UPDATE'
            );
            $stmt->execute([':id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new RuntimeException('Gatepass item not found.');
            }
            if ((int)$item['is_returnable'] !== 1) {
                throw new RuntimeException('This item is not returnable.');
            }

            $original = max(0, (int)$item['quantity']);
            $alreadyReturned = max(0, (int)$item['returned_quantity']);
            $remaining = $original - $alreadyReturned;

            if ($remaining < 1 || $quantity > $remaining) {
                throw new RuntimeException('Return quantity exceeds the remaining returnable quantity.');
            }

            $newReturned = $alreadyReturned + $quantity;

            $update = $this->db->prepare(
                'UPDATE gatepass_items
                 SET returned_quantity = :returned
                 WHERE id = :id AND returned_quantity = :expected'
            );
            $update->execute([
                ':returned' => $newReturned,
                ':id' => $itemId,
                ':expected' => $alreadyReturned,
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Item return changed concurrently. Please retry.');
            }

            $history = $this->db->prepare(
                'INSERT INTO gatepass_item_returns
                    (gatepass_item_id, gatepass_id, quantity_returned, actor_user_id, return_reference, notes)
                 VALUES (:item_id, :gatepass_id, :quantity, :actor, :reference, :notes)'
            );
            $history->execute([
                ':item_id' => $itemId,
                ':gatepass_id' => (int)$item['gatepass_id'],
                ':quantity' => $quantity,
                ':actor' => $actorUserId,
                ':reference' => $reference,
                ':notes' => $notes,
            ]);

            $this->db->commit();

            Audit::log('gatepass.item_returned', 'gatepass', (int)$item['gatepass_id'], [
                'item_id' => $itemId,
                'quantity' => $quantity,
                'returned_total' => $newReturned,
                'actor_user_id' => $actorUserId,
            ]);

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function outstanding(int $gatepassId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, gatepass_id, item_name, description, quantity, serial_number,
                    is_returnable, returned_quantity,
                    (quantity - returned_quantity) AS outstanding_quantity
             FROM gatepass_items
             WHERE gatepass_id = :gatepass_id
               AND is_returnable = 1
               AND returned_quantity < quantity
             ORDER BY id ASC'
        );
        $stmt->execute([':gatepass_id' => $gatepassId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
