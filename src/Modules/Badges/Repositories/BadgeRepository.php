<?php

declare(strict_types=1);

namespace App\Modules\Badges\Repositories;

use App\Core\DB;
use PDO;

final class BadgeRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    // FIX: removed int $tenantId param; INSERT had ::visit_id (double colon) — fixed to :visit_id
    public function issue(int $visitId, string $badgeCode): int
    {
        // Deactivate any previous active badge
        $this->db->prepare("
            UPDATE visit_badges
            SET is_active   = 0,
                returned_at = NOW()
            WHERE visit_id  = :visit_id
              AND is_active = 1
        ")->execute([':visit_id' => $visitId]);

        // FIX: was ::visit_id (double colon SQL typo)
        $stmt = $this->db->prepare("
            INSERT INTO visit_badges (visit_id, badge_code, printed_at, is_active)
            VALUES (:visit_id, :badge_code, NOW(), 1)
        ");

        $stmt->execute([
            ':visit_id'   => $visitId,
            ':badge_code' => $badgeCode,
        ]);

        return (int) $this->db->lastInsertId();
    }

    // FIX: removed int $tenantId param
    public function returnActiveBadge(int $visitId): void
    {
        $this->db->prepare("
            UPDATE visit_badges
            SET is_active   = 0,
                returned_at = NOW()
            WHERE visit_id  = :visit_id
              AND is_active = 1
        ")->execute([':visit_id' => $visitId]);
    }

    // FIX: removed int $tenantId param
    public function hasActiveBadge(int $visitId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM visit_badges
            WHERE visit_id    = :visit_id
              AND is_active   = 1
              AND returned_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':visit_id' => $visitId]);
        return (bool) $stmt->fetchColumn();
    }

    // FIX: removed int $tenantId param
    public function findActiveByVisit(int $visitId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM visit_badges
            WHERE visit_id = :visit_id AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':visit_id' => $visitId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
