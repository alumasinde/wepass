<?php

use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SuperAdminMiddleware;

use App\Modules\MasterAdmin\Controllers\MasterLoginController;
use App\Modules\MasterAdmin\Controllers\MasterLogoutController;
use App\Modules\Tenant\Controllers\TenantController;

use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\LogoutController;
use App\Modules\Auth\Controllers\PasswordController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Gatepass\Controllers\GatepassController;
use App\Modules\Gatepass\Controllers\GateScanController;
use App\Modules\Visitors\Controllers\VisitorController;
use App\Modules\Visits\Controllers\VisitController;
use App\Modules\Badges\Controllers\BadgeController;
use App\Modules\Settings\Controllers\SettingsController;
use App\Modules\Settings\Controllers\CompanySettingController;
use App\Modules\Settings\Controllers\ThemeSettingController;
use App\Modules\Settings\Controllers\DelegationController;
use App\Modules\Settings\Controllers\UserManagementController;
use App\Modules\Reports\Controllers\ReportController;
use App\Modules\Settings\Controllers\GatepassSettingController;
use App\Modules\Settings\Controllers\BadgeSettingController;
use App\Modules\Roles\Controllers\RoleController;
use App\Modules\Roles\Controllers\UserRoleController;
use App\Modules\Approval\Controllers\ApprovalController;
use App\Modules\Settings\Controllers\WorkflowController;
use App\Modules\Settings\Controllers\GatepassTypeController;
use App\Modules\Settings\Controllers\GatepassRuleController;
use App\Modules\Settings\Controllers\DepartmentController;



$router = new Router();
$auth = [AuthMiddleware::class];

// ── Rate-limit thresholds — tunable in config.ini [security], no code change needed ──
$loginLimit  = [RateLimitMiddleware::class, 'login',  (int) config('security.login_max_attempts', 5),  (int) config('security.login_lockout_seconds', 300)];
$resetLimit  = [RateLimitMiddleware::class, 'reset',  (int) config('security.reset_max_attempts', 5),  (int) config('security.reset_lockout_seconds', 900)];
$scanLimit   = [RateLimitMiddleware::class, 'gate-scan', (int) config('security.scan_max_attempts', 60), (int) config('security.scan_lockout_seconds', 60)];
// ^ currently unused — the /gatepasses/scan routes it was for are parked below, see the note there.
$approvalLimit = [RateLimitMiddleware::class, 'approval-action', (int) config('security.approval_max_attempts', 30), (int) config('security.approval_lockout_seconds', 60)];
$adminMutationLimit = [RateLimitMiddleware::class, 'admin-mutation', (int) config('security.admin_mutation_max_attempts', 60), (int) config('security.admin_mutation_lockout_seconds', 60)];

// ── Permission-gated middleware sets ──────────────────────────
// Each pairs AuthMiddleware (must be logged in) with
// PermissionMiddleware (must hold the named permission — seeded
// from config/permissions.php). Only mutating actions with a
// matching seeded permission key are gated this way; see the note
// at the bottom of this file for what was intentionally left out.
$gpCreate   = [AuthMiddleware::class, [PermissionMiddleware::class, 'gatepass.create']];
$gpUpdate   = [AuthMiddleware::class, [PermissionMiddleware::class, 'gatepass.update']];
$gpDelete   = [AuthMiddleware::class, [PermissionMiddleware::class, 'gatepass.delete']];
$gpCheckin  = [AuthMiddleware::class, [PermissionMiddleware::class, 'gatepass.checkin']];
$gpCheckout = [AuthMiddleware::class, [PermissionMiddleware::class, 'gatepass.checkout']];

$visitorCreate    = [AuthMiddleware::class, [PermissionMiddleware::class, 'visitors.create']];
$visitorUpdate    = [AuthMiddleware::class, [PermissionMiddleware::class, 'visitors.update']];
$visitorBlacklist = [AuthMiddleware::class, [PermissionMiddleware::class, 'visitors.blacklist']];

// Config-mutation sets (settings/roles/users admin screens) also
// carry $adminMutationLimit — previously only login/reset/scan/
// approve/reject were throttled at all; a compromised admin session
// (or a stolen one) could otherwise hammer role/permission/user
// changes with no rate limit whatsoever. Tunable via config.ini
// [security] admin_mutation_max_attempts/admin_mutation_lockout_seconds
// (defaults 60/60s — generous enough not to interfere with normal
// admin use, since these screens aren't hit anywhere near as often
// as login).
$usersCreate = [AuthMiddleware::class, [PermissionMiddleware::class, 'users.create'], $adminMutationLimit];
$usersUpdate = [AuthMiddleware::class, [PermissionMiddleware::class, 'users.update'], $adminMutationLimit];

$rolesCreate = [AuthMiddleware::class, [PermissionMiddleware::class, 'roles.create'], $adminMutationLimit];
$rolesUpdate = [AuthMiddleware::class, [PermissionMiddleware::class, 'roles.update'], $adminMutationLimit];
$rolesAssign = [AuthMiddleware::class, [PermissionMiddleware::class, 'roles.assign'], $adminMutationLimit];

$settingsUpdate = [AuthMiddleware::class, [PermissionMiddleware::class, 'settings.update'], $adminMutationLimit];

$auditView = [AuthMiddleware::class, [PermissionMiddleware::class, 'audit.view']];

// ── Master admin (platform/tenant-management panel) ──────────
// See TenantLoginController::guardDeployment() — these routes
// refuse to operate on any deployment with a [tenant] code set,
// so leaving them registered on a real client install is inert.
$superAdmin = [AuthMiddleware::class, SuperAdminMiddleware::class];

$router->get('/master/login', [MasterLoginController::class, 'index']);
$router->post('/master/login', [MasterLoginController::class, 'store'], [$loginLimit]);
$router->post('/master/logout', [MasterLogoutController::class, '__invoke'], $auth);

$router->get('/master/tenants', [TenantController::class, 'index'], $superAdmin);
$router->get('/master/tenants/create', [TenantController::class, 'create'], $superAdmin);
$router->post('/master/tenants', [TenantController::class, 'store'], $superAdmin);

$router->post('/master/tenants/test-connection', [TenantController::class, 'testConnection'], $superAdmin);

$router->get('/master/tenants/{id}/connection', [TenantController::class, 'editConnection'], $superAdmin);
$router->post('/master/tenants/{id}/connection', [TenantController::class, 'updateConnection'], $superAdmin);
$router->post('/master/tenants/{id}/connection/clear', [TenantController::class, 'clearConnection'], $superAdmin);

//Guest Routes
$router->get('/', [LoginController::class, 'index']);
$router->get('/login', [LoginController::class, 'index']);
$router->post('/login', [LoginController::class, 'store'], [$loginLimit]);

$router->get('/forgot-password', [PasswordController::class, 'forgot']);
$router->post('/forgot-password', [PasswordController::class, 'sendReset'], [$resetLimit]);

$router->get('/reset-password', [PasswordController::class, 'resetForm']);
$router->post('/reset-password', [PasswordController::class, 'reset'], [$resetLimit]);

//Dashboard Routes

$router->get('/dashboard', [DashboardController::class, 'index'], $auth);
$router->get('/dashboard/charts', [DashboardController::class, 'charts'], $auth);


//Gatepasses
$router->get('/gatepasses', [GatepassController::class, 'index'], $auth);
$router->get('/gatepasses/create', [GatepassController::class, 'create'], $auth);

// Gate scanning (GateScanController) is intentionally NOT routed
// right now — it renders Gatepass::scan / Gatepass::scan_result
// views that were never built, so it would 404/error on first use.
// Parked until that's built properly; check-in/check-out below
// (GatepassController::checkIn/checkOut) are the working flow in
// the meantime. The controller class itself is left in place under
// src/Modules/Gatepass/Controllers/GateScanController.php — re-add
// these two routes (and $scanLimit below) once its views exist.
// $router->get('/gatepasses/scan', [GateScanController::class, 'index'], $auth);
// $router->post('/gatepasses/scan', [GateScanController::class, 'process'], [...$auth, $scanLimit]);

$router->post('/gatepasses', [GatepassController::class, 'store'], $gpCreate);

// NOTE: the dynamic /gatepasses/{id}, {id}/edit, {id} (update), and
// {id}/delete routes used to be registered here too, duplicating the
// "KEEP LAST" block further down. Router::add() keys routes by exact
// path per method, so the later registration silently won - this
// block never actually ran for those four routes. Removed rather
// than left as dead, confusing duplication; see the single canonical
// registration further down.
// ==============================
// APPROVAL ROUTES
// ==============================

$router->get('/approvals', [ApprovalController::class, 'index'], $auth);
$router->get('/approvals/{id}', [ApprovalController::class, 'show'], $auth);

// Permission is enforced once, inside the controller via
// ApprovalPolicy::approve()/reject() ('approval.approve' /
// 'approval.reject') — not duplicated here with a different key.
// Two different permission keys gating the same action meant a
// role could satisfy one and silently fail the other.
$router->get('/approvals/{id}/approve', [ApprovalController::class, 'approve'], $auth);
$router->post('/approvals/{id}/approve', [ApprovalController::class, 'approve'], [...$auth, $approvalLimit]);

$router->get('/approvals/{id}/reject', [ApprovalController::class, 'reject'], $auth);
$router->post('/approvals/{id}/reject', [ApprovalController::class, 'reject'], [...$auth, $approvalLimit]);

/*
|--------------------------------------------------------------------------
| Visitors Module
|--------------------------------------------------------------------------
*/

$router->get('/visitors', [VisitorController::class, 'index'], $auth);
$router->get('/visitors/create', [VisitorController::class, 'create'], $auth);
$router->post('/visitors', [VisitorController::class, 'store'], $visitorCreate);
$router->post('/visitors/{id}/blacklist', [VisitorController::class, 'blacklist'], $visitorBlacklist);
$router->post('/visitors/{id}/unblacklist', [VisitorController::class, 'unblacklist'], $visitorBlacklist);


/*
|--------------------------------------------------------------------------
| Visits Module
|--------------------------------------------------------------------------
*/

$router->get('/visits', [VisitController::class, 'index'], $auth);
$router->get('/visits/create', [VisitController::class, 'create'], $auth);
$router->post('/visits', [VisitController::class, 'store'], $auth);
$router->get('/visitors/{id}', [VisitorController::class, 'view'], $auth);
$router->get('/visitors/{id}/edit', [VisitorController::class, 'edit'], $visitorUpdate);
$router->post('/visitors/{id}/update', [VisitorController::class, 'update'], $visitorUpdate);
$router->post('/visits/{id}/checkin', [VisitController::class, 'checkIn'], $auth);
$router->post('/visits/{id}/checkout', [VisitController::class, 'checkOut'], $auth);


/*
|--------------------------------------------------------------------------
| Badges Module
|--------------------------------------------------------------------------
*/

$router->post('/badges/{id}/issue', [BadgeController::class, 'issue'], $auth);
$router->post('/badges/{id}/return', [BadgeController::class, 'return'], $auth);


//Roles
$router->get('/roles', [RoleController::class, 'index'], $auth);
$router->get('/roles/create', [RoleController::class, 'create'], $rolesCreate);
$router->post('/roles', [RoleController::class, 'store'], $rolesCreate);
$router->get('/roles/{id}/edit', [RoleController::class, 'edit'], $rolesUpdate);
$router->post('/roles/{id}', [RoleController::class, 'update'], $rolesUpdate);
$router->post('/roles/{id}/delete', [RoleController::class, 'delete'], $rolesUpdate);

$router->get('/roles/{id}/permissions', [RoleController::class, 'permissions'], $rolesUpdate);
$router->post('/roles/{id}/permissions', [RoleController::class, 'updatePermissions'], $rolesUpdate);

$router->get('/users/{id}/roles', [UserRoleController::class, 'index'], $auth);
$router->post('/users/{id}/roles', [UserRoleController::class, 'update'], $rolesAssign);

//Settings

$router->get('/settings', [SettingsController::class, 'index'], $auth);

$router->get('/settings/company', [CompanySettingController::class, 'index'], $auth);
$router->post('/settings/company', [CompanySettingController::class, 'update'], $settingsUpdate);

// FIX: this route never existed — TenantController::uploadLogo() was
// completely unreachable, on top of the tenant-id and upload-path
// bugs fixed alongside it.
$router->post('/settings/company/logo', [TenantController::class, 'uploadLogo'], $settingsUpdate);

$router->get('/settings/theme', [ThemeSettingController::class, 'index'], $settingsUpdate);
$router->post('/settings/theme', [ThemeSettingController::class, 'update'], $settingsUpdate);
$router->post('/settings/theme/reset', [ThemeSettingController::class, 'reset'], $settingsUpdate);

$router->get('/settings/gatepass-numbering', [GatepassSettingController::class, 'index'], $auth);
$router->post('/settings/gatepass-numbering', [GatepassSettingController::class, 'update'], $settingsUpdate);

$router->get('/settings/badge-numbering', [BadgeSettingController::class, 'index'], $auth);
$router->post('/settings/badge-numbering', [BadgeSettingController::class, 'update'], $settingsUpdate);

$router->get('/settings/users', [UserManagementController::class, 'index'], $auth);
$router->get('/settings/users/create', [UserManagementController::class, 'create'], $usersCreate);
$router->post('/settings/users', [UserManagementController::class, 'store'], $usersCreate);

$router->get('/settings/users/{id}/edit', [UserManagementController::class, 'edit'], $usersUpdate);

$router->post('/settings/users/{id}', [UserManagementController::class, 'update'], $usersUpdate);

// gatepass-types, departments, and workflows now gated behind
// settings.update, same as every other admin config screen
// (company, gatepass-numbering, badge-numbering). Previously
// auth-only — any logged-in user, any role, could create workflows,
// change which workflow a gatepass type uses, or edit departments.
$router->get('/settings/gatepass-types', [GatepassTypeController::class, 'index'], $settingsUpdate);
$router->get('/settings/gatepass-types/create', [GatepassTypeController::class, 'create'], $settingsUpdate);
$router->get('/settings/gatepass-types/{id}/edit', [GatepassTypeController::class, 'edit'], $settingsUpdate);

$router->post('/settings/gatepass-types/store', [GatepassTypeController::class, 'store'], $settingsUpdate);
// FIX: removed the duplicate id-less '/settings/gatepass-types/update'
// registration that sat alongside this one — dead, confusing code
// (nothing ever called it; the JS always calls the id-based URL below).
// routes.php
$router->post('/settings/gatepass-types/{id}/update', [GatepassTypeController::class, 'update'], $settingsUpdate);

$router->get('/settings/gatepass-rules', [GatepassRuleController::class, 'index'], $settingsUpdate);
$router->post('/settings/gatepass-rules', [GatepassRuleController::class, 'update'], $settingsUpdate);

$router->get('/settings/departments', [DepartmentController::class, 'index'], $settingsUpdate);
$router->post('/settings/departments/create',[DepartmentController::class, 'store'], $settingsUpdate);
$router->post('/settings/departments/update',[DepartmentController::class, 'update'],$settingsUpdate);
$router->post('/settings/departments/toggle',[DepartmentController::class, 'toggle'],$settingsUpdate);
$router->post('/settings/departments/delete',[DepartmentController::class, 'delete'],$settingsUpdate);

// ==============================
// WORKFLOW SETTINGS
// ==============================

$router->get('/settings/workflows', [WorkflowController::class, 'index'], $settingsUpdate);
$router->get('/settings/workflows/create', [WorkflowController::class, 'create'], $settingsUpdate);
$router->post('/settings/workflows', [WorkflowController::class, 'store'], $settingsUpdate);

$router->get('/settings/workflows/{id}/edit', [WorkflowController::class, 'edit'], $settingsUpdate);
$router->post('/settings/workflows/{id}/update', [WorkflowController::class, 'update'], $settingsUpdate);

$router->get('/settings/workflows/{id}/steps', [WorkflowController::class, 'steps'], $settingsUpdate);
$router->post('/settings/workflows/{id}/steps', [WorkflowController::class, 'storeStep'], $settingsUpdate);

$router->get('/settings/workflows/{id}/steps/{stepId}/edit', [WorkflowController::class, 'editStep'], $settingsUpdate);
$router->post('/settings/workflows/{id}/steps/{stepId}/update', [WorkflowController::class, 'updateStep'], $settingsUpdate);

$router->get('/settings/workflows/{id}/steps/{stepId}/approvers', [WorkflowController::class, 'stepApprovers'], $settingsUpdate);
$router->post('/settings/workflows/{id}/steps/{stepId}/approvers', [WorkflowController::class, 'storeStepApprovers'], $settingsUpdate);

$router->get('/settings/workflows/{id}/assign', [WorkflowController::class, 'assign'], $settingsUpdate);
$router->post('/settings/workflows/{id}/assign', [WorkflowController::class, 'storeAssignment'], $settingsUpdate);

// API — read-only list of active workflow names for form dropdowns; left auth-only deliberately
$router->get('/api/workflows', [WorkflowController::class, 'list'], $auth);

$router->get('/settings/users/profile', [UserManagementController::class, 'profile'] , $auth);
$router->post('/settings/users/profile', [UserManagementController::class, 'updateProfile'] , $auth);

$router->get('/settings/delegation', [DelegationController::class, 'index'], $auth);
$router->post('/settings/delegation', [DelegationController::class, 'update'], $auth);
$router->post('/settings/delegation/clear', [DelegationController::class, 'clear'], $auth);


/*
|--------------------------------------------------------------------------
| Reports Module
|--------------------------------------------------------------------------
*/

$router->get('/reports', [ReportController::class, 'index'], $auth);

$router->get('/reports/gatepasses', [ReportController::class, 'gatepasses'], $auth);
$router->get('/reports/visitors', [ReportController::class, 'visitors'], $auth);
$router->get('/reports/visits', [ReportController::class, 'visits'], $auth);
$router->get('/reports/audit-logs', [ReportController::class, 'auditLogs'], $auditView);

$router->get('/reports/gatepasses/export', [ReportController::class, 'exportGatepasses'], $auth);
$router->get('/reports/visitors/export', [ReportController::class, 'exportVisitors'], $auth);
$router->get('/reports/visits/export', [ReportController::class, 'exportVisits'], $auth);
$router->get('/reports/audit-logs/export', [ReportController::class, 'exportAuditLogs'], $auditView);

// Gatepass Check-In / Check-Out
$router->post('/gatepasses/{id}/checkin', [GatepassController::class, 'checkIn'], $gpCheckin);
$router->post('/gatepasses/{id}/checkout', [GatepassController::class, 'checkOut'], $gpCheckout);

//Dynamic Gatepass Routes (KEEP LAST)

$router->get('/gatepasses/{id}', [GatepassController::class, 'show'], $auth);
$router->get('/gatepasses/{id}/edit', [GatepassController::class, 'edit'], $gpUpdate);
$router->post('/gatepasses/{id}', [GatepassController::class, 'update'], $gpUpdate);
$router->post('/gatepasses/{id}/delete', [GatepassController::class, 'delete'], $gpDelete);

//Logout Routes
$router->post(
    path: '/logout',
    handler: [LogoutController::class, '__invoke'],
    middleware: $auth // optional auth middleware
);

// ==============================================================
// Permission coverage note (production-readiness patch)
// ==============================================================
// Gated with PermissionMiddleware above: gatepass.{create,update,
// delete,approve,checkin,checkout}, visitors.{create,update,
// blacklist}, users.{create,update}, roles.{create,update,assign},
// settings.update, audit.view — all match keys already seeded from
// config/permissions.php, so nobody is locked out by a typo'd key.
//
// Left as AuthMiddleware-only (logged-in required, no fine-grained
// permission check): all GET/list/show routes (read access is
// gated in views via the new can() helper where you want it),
// plus the gatepass-types, departments, and workflows modules,
// which have no seeded permission module at all yet. Add modules
// for those in config/permissions.php and reseed if you want them
// locked down the same way.
return $router;