<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Gatepass\Repositories\GatepassStatusRepository;
use App\Modules\Gatepass\Services\GatepassWorkflow;
use App\Modules\Settings\Services\TenantSettingService;

/**
 * Settings -> Gatepass Rules — configurable version of what used to
 * be hardcoded in GatepassWorkflow::eligibility(): which gatepass
 * statuses allow check-out, which allow check-in, and whether
 * check-in requires the gatepass to be marked returnable.
 *
 * Deliberately does NOT let an admin redefine the underlying SEQUENCE
 * logic itself (e.g. there's no way to make check-in possible before
 * check-out has ever happened) — that protection lives in
 * GatepassWorkflow itself and stays enforced regardless of what's
 * configured here. This page only controls WHICH statuses count,
 * not whether the ordering rule applies at all.
 */
class GatepassRuleController
{
    private TenantSettingService $settings;
    private GatepassStatusRepository $statusRepo;

    private const SETTING_KEY = 'gatepass_workflow_rules';

    public function __construct()
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
        $this->settings   = new TenantSettingService();
        $this->statusRepo = new GatepassStatusRepository();
    }

    public function index(Request $request): void
    {
        $rules    = $this->settings->get(self::SETTING_KEY, GatepassWorkflow::DEFAULT_RULES);
        $statuses = $this->statusRepo->findAll();

        View::render('Settings::gatepass-rules', [
            'title'    => 'Gatepass Rules',
            'rules'    => $rules,
            'statuses' => $statuses,
            'flash'    => $_SESSION['flash'] ?? null,
        ], 'app');

        unset($_SESSION['flash']);
    }

    public function update(Request $request): void
    {
        $before = $this->settings->get(self::SETTING_KEY, GatepassWorkflow::DEFAULT_RULES);

        $checkoutStatuses = array_map('strtolower', (array) $request->input('checkout_statuses', []));
        $checkinStatuses  = array_map('strtolower', (array) $request->input('checkin_statuses', []));
        $requiresReturnable = !empty($request->input('checkin_requires_returnable'));

        // At least one status must be able to trigger check-out, or
        // no gatepass could ever leave PENDING/APPROVED at all — a
        // completely empty configuration would quietly break every
        // gatepass on this tenant, not just look unusual.
        if (empty($checkoutStatuses)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'At least one status must allow Check-Out — saving an empty list would block check-out entirely for every gatepass.'];
            header('Location: /settings/gatepass-rules');
            exit;
        }

        $rules = [
            'checkout_statuses'           => array_values($checkoutStatuses),
            'checkin_statuses'            => array_values($checkinStatuses),
            'checkin_requires_returnable' => $requiresReturnable,
        ];

        $this->settings->set(self::SETTING_KEY, $rules);

        // Tenant-wide, controls whether check-in/check-out works at
        // all — logged the same way any other security-sensitive
        // setting change already is in this app.
        \App\Core\Audit::log(
            action: 'gatepass_rules.updated',
            entityType: 'tenant_setting',
            entityId: 0,
            metadata: ['before' => $before, 'after' => $rules]
        );

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Gatepass rules updated.'];
        header('Location: /settings/gatepass-rules');
        exit;
    }
}
