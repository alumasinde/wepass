<?php

namespace App\Modules\Audit\Repositories;

use PDO;

class AuditLogRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Persist one audit entry.
     *
     * @param int|null    $userId
     * @param string      $actorName  Human-readable name
     * @param string      $action     Machine-readable action key
     * @param string|null $message    Human-readable sentence
     * @param string|null $entityType
     * @param int|null    $entityId
     * @param array|null  $metadata
     * @param string|null $ipAddress
     */
    public function log(
        ?int    $userId,
        string  $actorName,
        string  $action,
        ?string $message    = null,
        ?string $entityType = null,
        ?int    $entityId   = null,
        ?array  $metadata   = null,
        ?string $ipAddress  = null
    ): void {
        // FIX: was `(::user_id, :action, ...)` — double colon typo on :user_id
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs
                (user_id, action, message, entity_type, entity_id, metadata, ip_address)
            VALUES
                (:user_id, :action, :message, :entity_type, :entity_id, :metadata, :ip_address)
        ");

        $stmt->execute([
            ':user_id'     => $userId,
            ':action'      => $action,
            ':message'     => $message,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
            ':metadata'    => $this->encodeMetadata($metadata),
            ':ip_address'  => $ipAddress,
        ]);
    }

    /**
     * Paginated log retrieval.
     *
     * @param  array $filters  Supported keys: user_id, action, entity_type, entity_id, date_from, date_to
     * @return array{items: array, total: int}
     */
    public function findByTenant(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $total = (int) $this->db->prepare("SELECT COUNT(*) FROM audit_logs al WHERE {$where}")
                                ->execute($params)
                                ?: 0;

        // Re-run for count properly
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs al WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT
                al.id, al.action, al.message, al.entity_type, al.entity_id,
                al.metadata, al.ip_address, al.created_at,
                u.first_name, u.last_name,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                u.email AS user_email
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE {$where}
            ORDER BY al.created_at DESC
            LIMIT  :limit
            OFFSET :offset
        ");

        $stmt->execute(array_merge($params, [':limit' => $limit, ':offset' => $offset]));

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    // ── PRIVATE ─────────────────────────────────────────────

    private function buildWhere(array $filters): array
    {
        // FIX: removed 'al.tenant_id = :tenant_id' — per-database isolation
        $clauses = ['1=1'];
        $params  = [];

        if (!empty($filters['user_id'])) {
            $clauses[] = 'al.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $clauses[] = 'al.action = :action';
            $params[':action'] = $filters['action'];
        }
        if (!empty($filters['entity_type'])) {
            $clauses[] = 'al.entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }
        if (!empty($filters['entity_id'])) {
            $clauses[] = 'al.entity_id = :entity_id';
            $params[':entity_id'] = (int) $filters['entity_id'];
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = 'al.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'al.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        return [implode(' AND ', $clauses), $params];
    }

    private function encodeMetadata(?array $metadata): ?string
    {
        return empty($metadata)
            ? null
            : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
