<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Gatepass\Services\GateScanDecisionService;
use App\Modules\Gatepass\Services\GateScanExecutionService;
use App\Modules\Gatepass\Services\GateSecurityService;
use Throwable;

final class GateScanController
{
    private GateSecurityService $security;
    private GateScanDecisionService $decisions;
    private GateScanExecutionService $execution;

    public function __construct()
    {
        $this->security = new GateSecurityService();
        $this->decisions = new GateScanDecisionService($this->security);
        $this->execution = new GateScanExecutionService();
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
        if (!$user) Response::json(['success' => false, 'message' => 'Not authenticated.'], 401);

        $gateId = (int)$request->input('gate_id', $request->header('X-Gate-Id') ?? 0);
        $deviceUuid = trim((string)$request->input('device_uuid', $request->header('X-Device-UUID') ?? ''));
        $deviceSecret = trim((string)$request->input('device_secret', $request->header('X-Device-Secret') ?? ''));
        $qrToken = trim((string)$request->input('qr_token', ''));
        $scanType = strtoupper(trim((string)$request->input('scan_type', 'ENTRY')));
        $requestId = trim((string)$request->input('request_id', '')) ?: bin2hex(random_bytes(16));

        if ($gateId < 1 || $deviceUuid === '' || $deviceSecret === '' || $qrToken === '') {
            Response::json(['success' => false, 'message' => 'Scanner configuration or QR credential is missing.'], 422);
        }

        try {
            $existing = $this->security->findScanRequest($requestId);
            if ($existing !== null) {
                Response::json([
                    'success' => $existing['result'] === 'allowed',
                    'message' => 'This scan request has already been processed.',
                    'reason_code' => $existing['reason_code'],
                    'replayed_request' => true,
                ], $existing['result'] === 'allowed' ? 200 : 409);
            }

            $decision = $this->decisions->decide(
                $deviceUuid,
                $deviceSecret,
                $gateId,
                $qrToken,
                (int)$user['id'],
                $scanType
            );

            $this->security->recordScan([
                'gate_id' => $decision['gate_id'] ?? $gateId,
                'device_id' => $decision['device_id'] ?? 0,
                'guard_user_id' => (int)$user['id'],
                'gatepass_id' => $decision['gatepass_id'] ?? null,
                'visit_id' => null,
                'scan_type' => $scanType,
                'result' => strtolower($decision['decision']),
                'reason_code' => $decision['reason_code'],
                'request_id' => $requestId,
                'qr_token_hash' => $this->security->hashQrToken($qrToken),
                'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'metadata' => ['action' => $decision['action']],
            ]);

            if ($decision['decision'] !== 'ALLOW') {
                Response::json([
                    'success' => false,
                    'message' => 'Scan denied.',
                    'reason_code' => $decision['reason_code'],
                    'action' => 'NONE',
                ], 409);
            }

            $this->execution->execute($decision, (int)$user['id'], date('Y-m-d H:i:s'));
            $gatepass = $this->security->resolveQrToken($qrToken);

            Response::json([
                'success' => true,
                'message' => $decision['action'] === 'CHECK_IN'
                    ? 'Gatepass checked in successfully.'
                    : 'Gatepass checked out successfully.',
                'scan_type' => $scanType,
                'reason_code' => $decision['reason_code'],
                'action' => $decision['action'],
                'gate' => $decision['gate_id'],
                'gatepass' => [
                    'id' => (int)$decision['gatepass_id'],
                    'status' => $gatepass['status_id'] ?? null,
                ],
                'replayed_request' => false,
            ]);
        } catch (Throwable $e) {
            error_log('GateScanController: '.$e->getMessage());
            Response::json([
                'success' => false,
                'message' => config('app.debug', false) ? $e->getMessage() : 'Could not process this gatepass.',
            ], 400);
        }
    }
}
