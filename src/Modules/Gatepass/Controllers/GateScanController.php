<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Gatepass\Services\GateSecurityService;
use App\Modules\Gatepass\Services\GatepassService;
use App\Modules\Gatepass\Services\ScanIdempotencyService;
use Throwable;

final class GateScanController
{
    private GateSecurityService $security;
    private GatepassService $gatepasses;
    private ScanIdempotencyService $idempotency;

    public function __construct()
    {
        $this->security = new GateSecurityService();
        $this->gatepasses = new GatepassService();
        $this->idempotency = new ScanIdempotencyService();
    }

    public function index(Request $request): void
    {
        View::render('Gatepass::scan', [
            'title' => 'Gate Scanner',
            'user' => $_SESSION['user'] ?? null,
        ], 'app');
    }

    public function process(Request $request): never
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            Response::json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $gateId = (int) $request->input('gate_id', $request->header('X-Gate-Id') ?? 0);
        $uuid = trim((string) $request->input('device_uuid', $request->header('X-Device-UUID') ?? ''));
        $secret = trim((string) $request->input('device_secret', $request->header('X-Device-Secret') ?? ''));
        $token = trim((string) $request->input('qr_token', ''));
        $requestId = trim((string) $request->input('request_id', '')) ?: bin2hex(random_bytes(16));

        if ($gateId < 1 || $uuid === '' || $secret === '' || $token === '') {
            Response::json(['success' => false, 'message' => 'Scanner configuration or QR credential is missing.'], 422);
        }

        $device = $this->security->authenticateDevice($uuid, $secret, $gateId, (int) $user['id']);
        if (!$device) {
            Response::json(['success' => false, 'message' => 'This device is not approved for this gate.'], 403);
        }

        $base = [
            'gate_id' => $gateId,
            'device_id' => (int) $device['device_id'],
            'guard_user_id' => $device['guard_user_id'] !== null
                ? (int) $device['guard_user_id']
                : (int) $user['id'],
            'request_id' => $requestId,
            'qr_token_hash' => $this->security->hashQrToken($token),
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];

        try {
            $claim = $this->idempotency->claim($requestId, $base);
            if (!$claim['claimed']) {
                $previous = $claim['event'];
                if ($previous['result'] === 'processing') {
                    Response::json([
                        'success' => false,
                        'message' => 'This scan request is already being processed.',
                        'reason_code' => 'SCAN_IN_PROGRESS',
                    ], 409);
                }

                Response::json([
                    'success' => $previous['result'] === 'allowed',
                    'message' => $previous['result'] === 'allowed'
                        ? 'Scan already processed.'
                        : 'This scan request was already rejected.',
                    'scan_type' => $previous['scan_type'],
                    'reason_code' => $previous['reason_code'],
                ], $previous['result'] === 'allowed' ? 200 : 409);
            }

            $eventId = (int) $claim['event_id'];
            $gatepass = $this->security->resolveQrToken($token);

            if (!$gatepass) {
                $this->idempotency->complete($eventId, [
                    'scan_type' => 'validation',
                    'result' => 'denied',
                    'reason_code' => 'QR_INVALID_OR_EXPIRED',
                ]);
                Response::json(['success' => false, 'message' => 'QR credential is invalid, expired, or revoked.'], 422);
            }

            $full = $this->gatepasses->find((int) $gatepass['id']) ?? $gatepass;
            $actions = $this->gatepasses->getAvailableActions($full);

            if (!empty($actions['can_checkin'])) {
                $this->gatepasses->checkIn((int) $gatepass['id'], (int) $user['id']);
                $type = 'checkin';
                $message = 'Gatepass checked in successfully.';
            } elseif (!empty($actions['can_checkout'])) {
                $this->gatepasses->checkOut((int) $gatepass['id'], (int) $user['id']);
                $type = 'checkout';
                $message = 'Gatepass checked out successfully.';
            } else {
                $this->idempotency->complete($eventId, [
                    'gatepass_id' => (int) $gatepass['id'],
                    'visit_id' => $gatepass['visit_id'] ?? null,
                    'scan_type' => 'validation',
                    'result' => 'denied',
                    'reason_code' => 'NO_ALLOWED_GATE_ACTION',
                ]);
                Response::json(['success' => false, 'message' => 'This gatepass cannot be processed.'], 409);
            }

            $this->idempotency->complete($eventId, [
                'gatepass_id' => (int) $gatepass['id'],
                'visit_id' => $gatepass['visit_id'] ?? null,
                'scan_type' => $type,
                'result' => 'allowed',
            ]);

            $updated = $this->gatepasses->find((int) $gatepass['id']);
            Response::json([
                'success' => true,
                'message' => $message,
                'scan_type' => $type,
                'gate' => $device['gate_name'],
                'gatepass' => [
                    'id' => (int) $updated['id'],
                    'number' => $updated['gatepass_number'],
                    'status' => $updated['status_name'] ?? null,
                    'status_code' => $updated['status_code'] ?? null,
                ],
            ]);
        } catch (Throwable $e) {
            error_log('GateScanController: ' . $e->getMessage());

            if (isset($eventId) && $eventId > 0) {
                try {
                    $this->idempotency->complete($eventId, [
                        'gatepass_id' => $gatepass['id'] ?? null,
                        'visit_id' => $gatepass['visit_id'] ?? null,
                        'scan_type' => 'validation',
                        'result' => 'error',
                        'reason_code' => 'PROCESSING_ERROR',
                    ]);
                } catch (Throwable $ignored) {
                    error_log('GateScanController: failed to finalize scan event: ' . $ignored->getMessage());
                }
            }

            Response::json([
                'success' => false,
                'message' => config('app.debug', false) ? $e->getMessage() : 'Could not process this gatepass.',
            ], 400);
        }
    }
}
