<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Repositories\AuditLogRepository;
use Throwable;

/**
 * AuditService — per-database isolation model.
 * tenant_id parameter removed from all methods.
 */
class AuditService
{
    private const MESSAGE_TEMPLATES = [
        // Auth
        'user.login'              => '{actor} logged in',
        'user.logout'             => '{actor} logged out',
        'user.login_failed'       => 'Failed login attempt for {actor}',
        'user.password_changed'   => '{actor} changed their password',
        'user.password_reset'     => 'Password reset requested for {actor}',
        'user.mfa_enabled'        => '{actor} enabled two-factor authentication',
        'user.mfa_disabled'       => '{actor} disabled two-factor authentication',
        // Dashboard
        'dashboard.viewed'        => '{actor} viewed the dashboard',
        // User management
        'user.created'            => '{actor} created user account for {target}',
        'user.updated'            => '{actor} updated user account for {target}',
        'user.deleted'            => '{actor} deleted user account for {target}',
        'user.role_changed'       => '{actor} changed role for {target}',
        'user.deactivated'        => '{actor} deactivated account for {target}',
        'user.reactivated'        => '{actor} reactivated account for {target}',
        // Gatepass
        'gatepass.viewed'         => '{actor} viewed gatepass {target}',
        'gatepass.list_viewed'    => '{actor} viewed the gatepass list',
        'gatepass.created'        => '{actor} created gatepass {target}',
        'gatepass.updated'        => '{actor} updated gatepass {target}',
        'gatepass.submitted'      => '{actor} submitted gatepass {target} for approval',
        'gatepass.approved'       => '{actor} approved gatepass {target}',
        'gatepass.rejected'       => '{actor} rejected gatepass {target}',
        'gatepass.cancelled'      => '{actor} cancelled gatepass {target}',
        'gatepass.deleted'        => '{actor} deleted gatepass {target}',
        'gatepass.checked_in'     => '{actor} checked in gatepass {target}',
        'gatepass.checked_out'    => '{actor} checked out gatepass {target}',
        // Department
        'department.created'      => '{actor} created department {target}',
        'department.updated'      => '{actor} updated department {target}',
        'department.deleted'      => '{actor} deleted department {target}',
        // Visits
        'visit.created'           => '{actor} created a visit',
        'visit.checkin'           => '{actor} checked in visit {target}',
        'visit.checkout'          => '{actor} checked out visit {target}',
        'visit.badge_issued'      => '{actor} issued badge for visit {target}',
        'visit.badge_returned'    => '{actor} returned badge for visit {target}',
        // Visitors
        'visitor.created'         => '{actor} registered visitor {target}',
        'visitor.updated'         => '{actor} updated visitor {target}',
        'visitor.blacklisted'     => '{actor} blacklisted visitor {target}',
        'visitor.unblacklisted'   => '{actor} removed visitor {target} from blacklist',
        // Tenant / settings
        'tenant.settings_updated' => '{actor} updated tenant settings',
        'tenant.plan_changed'     => '{actor} changed subscription plan',
        // System
        'system.backup_completed' => 'Scheduled backup completed',
        'system.cleanup_run'      => 'Scheduled cleanup job ran',
    ];

    public function __construct(private AuditLogRepository $repo) {}

    /**
     * Record an audit entry.
     *
     * FIX: removed int $tenantId as first parameter — per-database isolation.
     *
     * @param int|null    $userId
     * @param string      $actorName
     * @param string      $action
     * @param string|null $entityType
     * @param int|null    $entityId
     * @param array|null  $metadata
     * @param string|null $ipAddress
     */
    public function log(
        ?int    $userId,
        string  $actorName,
        string  $action,
        ?string $entityType = null,
        ?int    $entityId   = null,
        ?array  $metadata   = null,
        ?string $ipAddress  = null
    ): void {
        try {
            $message = $this->buildMessage($action, $actorName, $metadata);

            $this->repo->log(
                $userId,
                $actorName,
                $action,
                $message,
                $entityType,
                $entityId,
                $metadata,
                $ipAddress
            );
        } catch (Throwable $e) {
            error_log('[AuditService] Failed to write audit log: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve paginated audit logs.
     * FIX: removed int $tenantId param.
     *
     * @return array{items: array, total: int}
     */
    public function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->repo->findByTenant($filters, $limit, $offset);
    }

    // ── PRIVATE ─────────────────────────────────────────────

    private function buildMessage(string $action, string $actorName, ?array $metadata): string
    {
        $template = self::MESSAGE_TEMPLATES[$action] ?? '{actor} ' . $action;
        $target   = $metadata['target_label'] ?? '';

        return str_replace(['{actor}', '{target}'], [$actorName, $target], $template);
    }
}
