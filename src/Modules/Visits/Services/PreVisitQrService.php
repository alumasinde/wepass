<?php

declare(strict_types=1);

namespace App\Modules\Visits\Services;

use App\Core\Audit;
use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * Opaque, expiring QR credentials for scheduled visitor pre-check-in.
 * The raw token is returned only to the issuing caller and is never stored.
 */
final class PreVisitQrService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function issue(int $visitId, ?int $ttlSeconds = null): string
    {
        if ($visitId < 1) {
            throw new RuntimeException('Invalid visit.');
        }

        $ttlSeconds ??= (int) config('security.previsit_qr_ttl_seconds', 86400);
        if ($ttlSeconds < 60 || $ttlSeconds > 2592000) {
            throw new RuntimeException('Pre-visit QR lifetime is outside the allowed range.');
        }

        $visit = $this->findVisit($visitId);
        if (!$visit) {
            throw new RuntimeException('Visit not found.');
        }
        if ($visit['checkout_time'] !== null) {
            throw new RuntimeException('Completed visits cannot receive a pre-visit QR.');
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $stmt = $this->db->prepare(
            'UPDATE visits
             SET previsit_qr_token_hash = :hash,
                 previsit_qr_issued_at = NOW(),
                 previsit_qr_expires_at = :expires,
                 previsit_qr_revoked_at = NULL,
                 updated_at = NOW()
             WHERE id = :id AND checkout_time IS NULL'
        );
        $stmt->execute([':hash' => $hash, ':expires' => $expires, ':id' => $visitId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Pre-visit QR could not be issued.');
        }

        Audit::log('visit.previsit_qr_issued', 'visit', $visitId, [
            'expires_at' => $expires,
            'ttl_seconds' => $ttlSeconds,
        ]);

        return $token;
    }

    public function revoke(int $visitId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE visits
             SET previsit_qr_revoked_at = NOW(), updated_at = NOW()
             WHERE id = :id
               AND previsit_qr_token_hash IS NOT NULL
               AND previsit_qr_revoked_at IS NULL'
        );
        $stmt->execute([':id' => $visitId]);

        if ($stmt->rowCount() === 1) {
            Audit::log('visit.previsit_qr_revoked', 'visit', $visitId);
            return true;
        }
        return false;
    }

    public function resolve(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT
                v.id, v.visitor_id, v.department_id, v.host_user_id,
                v.visit_type_id, v.visit_status_id, v.purpose,
                v.contract_reference, v.escort_required,
                v.expected_in, v.expected_out,
                v.checkin_time, v.checkout_time,
                vis.first_name, vis.last_name, vis.phone, vis.email,
                vis.company_id, vc.name AS visitor_company,
                d.name AS department_name,
                u.first_name AS host_first_name, u.last_name AS host_last_name,
                vs.name AS status_name, vs.code AS status_code
             FROM visits v
             INNER JOIN visitors vis ON vis.id = v.visitor_id
             LEFT JOIN visitor_companies vc ON vc.id = vis.company_id
             LEFT JOIN departments d ON d.id = v.department_id
             LEFT JOIN users u ON u.id = v.host_user_id
             LEFT JOIN visit_statuses vs ON vs.id = v.visit_status_id
             WHERE v.previsit_qr_token_hash = :hash
               AND v.previsit_qr_revoked_at IS NULL
               AND (v.previsit_qr_expires_at IS NULL OR v.previsit_qr_expires_at > NOW())
               AND v.checkout_time IS NULL
               AND vis.is_blacklisted = 0
             LIMIT 1"
        );
        $stmt->execute([':hash' => hash('sha256', $token)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function findVisit(int $visitId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, checkout_time FROM visits WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $visitId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
