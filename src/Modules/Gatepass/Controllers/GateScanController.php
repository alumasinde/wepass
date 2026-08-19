<?php

namespace App\Modules\Gatepass\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\View;
use App\Modules\Gatepass\Services\GatepassService;

class GateScanController extends Controller
{
    private GatepassService $service;

    public function __construct()
    {
        $this->service = new GatepassService();
    }

    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
        return $_SESSION['user'];
    }

    public function index(): mixed
    {
        return View::render('Gatepass::scan', ['title' => 'Scan Gatepass'], 'app');
    }

    public function process(Request $request): mixed
    {
        $user = $this->user();
        $code = trim((string) $request->input('gatepass_number', ''));

        if ($code === '') {
            return $this->scanError('Invalid QR code.');
        }

        try {
            // FIX: was $this->service->findByNumber(0, $code) — removed stale 0 tenantId arg
            $gatepass = $this->service->findByNumber($code);

            if (!$gatepass) {
                return $this->scanError('Gatepass not found.');
            }

            $actualIn     = $gatepass['actual_in']    ?? null;
            $actualOut    = $gatepass['actual_out']   ?? null;
            $isReturnable = (int) ($gatepass['is_returnable'] ?? 0) === 1;

            if (!$actualIn) {
                // FIX: was $this->service->checkIn(0, (int) $gatepass['id'], $user['id'])
                $this->service->checkIn((int) $gatepass['id'], $user['id']);
                return $this->scanSuccess('Checked in successfully.');
            }

            if ($isReturnable && $actualIn && !$actualOut) {
                // FIX: was $this->service->markReturned(0, ...)
                $this->service->markReturned((int) $gatepass['id']);
                return $this->scanSuccess('Item returned successfully.');
            }

            if (!$actualOut) {
                // FIX: was $this->service->checkOut(0, ...)
                $this->service->checkOut((int) $gatepass['id'], $user['id']);
                return $this->scanSuccess('Checked out successfully.');
            }

            return $this->scanError('Gatepass process already completed.');

        } catch (\Throwable $e) {
            // Log the real error server-side; never echo internals
            // (stack traces, SQL, file paths) to the gate scanner UI.
            error_log('GateScanController: ' . $e->getMessage());

            return $this->scanError(
                config('app.debug', false)
                    ? $e->getMessage()
                    : 'Could not process this gatepass. Please try again or notify an administrator.'
            );
        }
    }

    private function scanSuccess(string $message): mixed
    {
        return View::render('Gatepass::scan_result', ['title' => 'Scan Result', 'message' => $message], 'app');
    }

    private function scanError(string $error): mixed
    {
        return View::render('Gatepass::scan', ['title' => 'Scan Gatepass', 'error' => $error], 'app');
    }
}
