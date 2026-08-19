<?php

namespace App\Modules\Gatepass\Policies;

use App\Core\Permission;

/**
 * GatepassPolicy — object-level authorization for a single gatepass
 * record. Route middleware (PermissionMiddleware) only checks the
 * coarse-grained permission key ("does this user have gatepass.update
 * at all?") — it has no idea which record is being acted on. This
 * class is what actually decides "is this user allowed to act on
 * THIS gatepass" and MUST be called by the controller for every
 * single-record action (view/update/delete). It was previously
 * defined but never invoked anywhere, which meant any user holding
 * the coarse permission could view/edit/delete ANY gatepass tenant-
 * wide — not just their own department's or their own record.
 */
class GatepassPolicy
{
    public function __construct(private Permission $permission) {}

    /**
     * Broad, cross-department visibility/action permission. Replaces
     * the old hardcoded role-name check
     * (in_array($role, ['admin', 'General Manager', 'superadmin']))
     * that used to live in GatepassService::list() — visibility is
     * now driven entirely by the DB-configured permission model, the
     * same as everything else in the app. A super admin always
     * passes via Permission::can()'s own bypass.
     */
    public function canViewAll(): bool
    {
        return $this->permission->can('gatepass.view_all');
    }

    public function create(): bool
    {
        return $this->permission->can('gatepass.create');
    }

    public function approve(): bool
    {
        return $this->permission->can('gatepass.approve');
    }

    /**
     * View a single gatepass. Allowed if the user can see everything
     * (gatepass.view_all), created the record themselves, or shares
     * its department — otherwise a bare gatepass.view permission
     * would let any authenticated staff member browse every other
     * department's gatepasses by guessing/incrementing IDs.
     */
    public function view(array $user, array $gatepass): bool
    {
        if (!$this->permission->can('gatepass.view')) {
            return false;
        }

        if ($this->canViewAll()) {
            return true;
        }

        if ((int) ($gatepass['created_by'] ?? 0) === (int) ($user['id'] ?? 0)) {
            return true;
        }

        return isset($user['department_id'], $gatepass['department_id'])
            && (int) $user['department_id'] === (int) $gatepass['department_id'];
    }

    /**
     * Edit a gatepass. Requires the base permission AND (own record
     * while still PENDING, OR cross-department override).
     */
    public function update(array $user, array $gatepass): bool
    {
        if (!$this->permission->can('gatepass.update')) {
            return false;
        }

        if ($this->canViewAll()) {
            return true;
        }

        return (int) ($gatepass['created_by'] ?? 0) === (int) ($user['id'] ?? 0)
            && strtoupper((string) ($gatepass['status_code'] ?? '')) === 'PENDING';
    }

    /**
     * Delete a gatepass. Deliberately stricter than update(): a
     * gatepass that has already been checked in represents a real
     * physical event (an item/visitor actually passed the gate) —
     * destroying that record destroys part of the audit trail, so
     * it's blocked here regardless of who's asking. Creators can
     * delete their own not-yet-checked-in PENDING request; the
     * cross-department override can clean up any not-yet-checked-in
     * record, but still can't delete history.
     */
    public function delete(array $user, array $gatepass): bool
    {
        if (!$this->permission->can('gatepass.delete')) {
            return false;
        }

        if (!empty($gatepass['actual_in'])) {
            return false;
        }

        if ($this->canViewAll()) {
            return true;
        }

        return (int) ($gatepass['created_by'] ?? 0) === (int) ($user['id'] ?? 0)
            && strtoupper((string) ($gatepass['status_code'] ?? '')) === 'PENDING';
    }
}
