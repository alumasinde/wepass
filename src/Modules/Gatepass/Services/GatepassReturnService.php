<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * Phase 4 return boundary.
 *
 * Return events are append-only while gatepass_items.returned_quantity
 * is maintained as the current aggregate. The gatepass itself moves to
 * RETURNED only when every returnable item has been fully returned.
 */
final class GatepassReturnService
{
    private PDO $db;
    private GatepassStateService $states;
    private bool $ownsTransaction;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
        $this->states = new GatepassStateService($this->db);
        $this->ownsTransaction = $db === null;
    }

    public function recordItemReturn(
        int $gatepassItemId,
        int $quantity,
        int $actorUserId,
        ?string $returnReference = null,
        ?string $notes = null
    ): bool {
        if ($gatepassItemId < 1 || $quantity < 1 || $actorUserId < 1) {
            throw new RuntimeException('Invalid return details.');
        }

        if ($this->ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $itemStmt = $this->db->prepare(
                'SELECT gi.id, gi.gatepass_id, gi.quantity, gi.returned_quantity,
                        gi.is_returnable, g.is_returnable AS gatepass_returnable,
                        s.code AS status_code
                 FROM gatepass_items gi
                 INNER JOIN gatepasses g ON g.id = gi.gatepass_id
                 INNER JOIN gatepass_statuses s ON s.id = g.status_id
                 WHERE gi.id = :item_id AND g.deleted_at IS NULL
                 FOR UPDATE'
            );
            $itemStmt->execute([':item_id' => $gatepassItemId]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new RuntimeException('Returnable item not found.');
            }
            if (!(int) $item['gatepass_returnable'] || !(int) $item['is_returnable']) {
                throw new RuntimeException('This item is not returnable.');
            }
            if (strtolower((string) $item['status_code']) !== 'checked_in') {
                throw new RuntimeException('Items can only be returned after the gatepass is checked in.');
            }

            $quantityIssued = (int) $item['quantity'];
            $quantityReturned = max(0, (int) $item['returned_quantity']);
            $remaining = $quantityIssued - $quantityReturned;

            if ($remaining < 1) {
                throw new RuntimeException('This item has already been fully returned.');
            }
            if ($quantity > $remaining) {
                throw new RuntimeException("Return quantity exceeds the remaining quantity ({$remaining}).");
            }

            $insert = $this->db->prepare(
                'INSERT INTO gatepass_item_returns
                    (gatepass_item_id, gatepass_id, quantity_returned, actor_user_id, return_reference, notes)
                 VALUES (:item_id, :gatepass_id, :quantity, :actor, :reference, :notes)'
            );
            $insert->execute([
                ':item_id' => $gatepassItemId,
                ':gatepass_id' => (int) $item['gatepass_id'],
                ':quantity' => $quantity,
                ':actor' => $actorUserId,
                ':reference' => $returnReference !== null ? trim($returnReference) : null,
                ':notes' => $notes !== null ? trim($notes) : null,
            ]);

            $newReturnedQuantity = $quantityReturned + $quantity;
            $updateItem = $this->db->prepare(
                'UPDATE gatepass_items
                 SET returned_quantity = :returned_quantity
                 WHERE id = :item_id'
            );
            $updateItem->execute([
                ':returned_quantity' => $newReturnedQuantity,
                ':item_id' => $gatepassItemId,
            ]);

            $allReturnedStmt = $this->db->prepare(
                'SELECT
                    COUNT(*) AS returnable_items,
                    COALESCE(SUM(quantity), 0) AS issued_quantity,
                    COALESCE(SUM(returned_quantity), 0) AS returned_quantity
                 FROM gatepass_items
                 WHERE gatepass_id = :gatepass_id AND is_returnable = 1'
            );
            $allReturnedStmt->execute([':gatepass_id' => (int) $item['gatepass_id']]);
            $totals = $allReturnedStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $fullyReturned =
                (int) ($totals['returnable_items'] ?? 0) > 0 &&
                (int) ($totals['issued_quantity'] ?? 0) <= (int) ($totals['returned_quantity'] ?? 0);

            if ($fullyReturned) {
                $gatepassId = (int) $item['gatepass_id'];
                $updateGatepass = $this->db->prepare(
                    'UPDATE gatepasses
                     SET is_fully_returned = 1, actual_return_date = NOW()
                     WHERE id = :id AND deleted_at IS NULL'
                );
                $updateGatepass->execute([':id' => $gatepassId]);

                $this->states->transition(
                    $gatepassId,
                    'checked_in',
                    'returned',
                    'RETURN_COMPLETE',
                    $actorUserId,
                    $notes,
                    [
                        'gatepass_item_id' => $gatepassItemId,
                        'quantity_returned' => $quantity,
                        'return_reference' => $returnReference,
                    ]
                );
            }

            if ($this->ownsTransaction) {
                $this->db->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
