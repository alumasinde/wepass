<?php

declare(strict_types=1);

namespace App\Modules\Visits\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Modules\Gatepass\Services\GateSecurityService;
use App\Modules\Visits\Services\PreVisitQrService;
use Throwable;

final class PreVisitQrController
{
    public function __construct(
        private PreVisitQrService $qr,
        private GateSecurityService $security
    ) {}

    /** Issue a visitor-facing pre-visit credential. */
    public function issue(Request $request, int $id): never
    {
        try {
            $token = $this->qr->issue($id);
            Response::json([
                'success' => true,
                'visit_id' => $id,
                'qr_token' => $token,
                'message' => 'Pre-visit QR issued. Store the token securely; it cannot be recovered later.',
            ]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function revoke(Request $request, int $id): never
    {
        try {
            if (!$this->qr->revoke($id)) {
                Response::json(['success' => false, 'message' => 'No active pre-visit QR credential found.'], 404);
            }
            Response::json(['success' => true, 'message' => 'Pre-visit QR revoked.']);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Gate-side validation only. This deliberately does not check the visitor in:
     * the guard can verify a scheduled visitor before the normal visit check-in.
     */
    public function validateAtGate(Request $request): never
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            Response::json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $gateId = (int) $request->input('gate_id', $request->header('X-Gate-Id') ?? 0);
        $uuid = trim((string) $request->input('device_uuid', $request->header('X-Device-UUID') ?? ''));
        $secret = trim((string) $request->input('device_secret', $request->header('X-Device-Secret') ?? ''));
        $token = trim((string) $request->input('qr_token', ''));

        if ($gateId < 1 || $uuid === '' || $secret === '' || $token === '') {
            Response::json(['success' => false, 'message' => 'Gate scanner configuration or pre-visit QR is missing.'], 422);
        }

        $device = $this->security->authenticateDevice($uuid, $secret, $gateId, (int) $user['id']);
        if (!$device) {
            Response::json(['success' => false, 'message' => 'This device is not approved for this gate.'], 403);
        }

        $visit = $this->qr->resolve($token);
        if (!$visit) {
            Response::json([
                'success' => false,
                'message' => 'Pre-visit QR is invalid, expired, revoked, completed, or belongs to a blacklisted visitor.',
                'reason_code' => 'PREVISIT_QR_INVALID',
            ], 422);
        }

        $now = time();
        $expectedIn = !empty($visit['expected_in']) ? strtotime((string) $visit['expected_in']) : null;
        $expectedOut = !empty($visit['expected_out']) ? strtotime((string) $visit['expected_out']) : null;
        $windowSeconds = max(0, (int) config('security.previsit_scan_window_seconds', 7200));

        if ($expectedIn !== null && $now < ($expectedIn - $windowSeconds)) {
            Response::json(['success' => false, 'message' => 'Visitor arrived too early for this scheduled visit.', 'reason_code' => 'PREVISIT_TOO_EARLY'], 409);
        }
        if ($expectedOut !== null && $now > $expectedOut) {
            Response::json(['success' => false, 'message' => 'This scheduled visit has expired.', 'reason_code' => 'PREVISIT_EXPIRED'], 409);
        }

        Response::json([
            'success' => true,
            'message' => 'Pre-visit validated. Proceed with visitor check-in.',
            'gate' => $device['gate_name'],
            'visit' => [
                'id' => (int) $visit['id'],
                'status' => $visit['status_name'] ?? null,
                'status_code' => $visit['status_code'] ?? null,
                'visitor' => [
                    'id' => (int) $visit['visitor_id'],
                    'name' => trim(($visit['first_name'] ?? '') . ' ' . ($visit['last_name'] ?? '')),
                    'phone' => $visit['phone'] ?? null,
                    'company' => $visit['visitor_company'] ?? null,
                ],
                'host' => trim(($visit['host_first_name'] ?? '') . ' ' . ($visit['host_last_name'] ?? '')),
                'department' => $visit['department_name'] ?? null,
                'expected_in' => $visit['expected_in'] ?? null,
                'expected_out' => $visit['expected_out'] ?? null,
                'purpose' => $visit['purpose'] ?? null,
                'escort_required' => (bool) $visit['escort_required'],
            ],
        ]);
    }
}
