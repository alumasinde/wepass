<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use RuntimeException;

/**
 * Phase 5: central gate decision boundary.
 * Keeps scanner controllers thin and makes every decision explicit.
 */
final class GateScanDecisionService
{
    public function __construct(private readonly GateSecurityService $security = new GateSecurityService())
    {
    }

    public function decide(
        string $deviceUuid,
        string $deviceSecret,
        int $gateId,
        string $qrToken,
        int $guardUserId,
        ?string $scanType = null
    ): array {
        $scanType = strtoupper(trim($scanType ?: 'ENTRY'));
        if (!in_array($scanType, ['ENTRY', 'EXIT'], true)) {
            return $this->deny('INVALID_SCAN_TYPE');
        }

        $device = $this->security->authenticateDevice($deviceUuid, $deviceSecret, $gateId, $guardUserId);
        if ($device === null) {
            return $this->deny('DEVICE_NOT_AUTHORIZED');
        }

        $token = trim($qrToken);
        if ($token === '') {
            return $this->deny('QR_EMPTY');
        }

        $gatepass = $this->security->resolveQrToken($token);
        if ($gatepass === null) {
            return $this->deny('QR_INVALID_OR_EXPIRED');
        }

        $status = strtoupper((string)($gatepass['status_code'] ?? ''));
        if (in_array($status, ['REJECTED', 'CANCELLED', 'EXPIRED', 'RETURNED'], true)) {
            return $this->deny('GATEPASS_NOT_ACTIVE', $gatepass, $device);
        }

        $now = time();
        if (!empty($gatepass['visit_start_at']) && strtotime((string)$gatepass['visit_start_at']) > $now) {
            return $this->deny('VISIT_NOT_STARTED', $gatepass, $device);
        }
        if (!empty($gatepass['visit_end_at']) && strtotime((string)$gatepass['visit_end_at']) < $now) {
            return $this->deny('VISIT_ENDED', $gatepass, $device);
        }

        $direction = strtoupper((string)($gatepass['direction'] ?? 'BOTH'));
        if ($direction !== 'BOTH') {
            if ($scanType === 'ENTRY' && !in_array($direction, ['INBOUND', 'IN', 'ENTRY'], true)) {
                return $this->deny('WRONG_DIRECTION', $gatepass, $device);
            }
            if ($scanType === 'EXIT' && !in_array($direction, ['OUTBOUND', 'OUT', 'EXIT'], true)) {
                return $this->deny('WRONG_DIRECTION', $gatepass, $device);
            }
        }

        if ($scanType === 'ENTRY') {
            if ($status === 'CHECKED_IN') return $this->deny('ALREADY_CHECKED_IN', $gatepass, $device);
            if (!in_array($status, ['APPROVED', 'CHECKED_OUT'], true)) {
                return $this->deny('ENTRY_NOT_ALLOWED', $gatepass, $device);
            }
        } else {
            if ($status !== 'CHECKED_IN') {
                return $this->deny('EXIT_NOT_ALLOWED', $gatepass, $device);
            }
        }

        return [
            'decision' => 'ALLOW',
            'reason_code' => 'VALID_GATEPASS',
            'action' => $scanType === 'ENTRY' ? 'CHECK_IN' : 'CHECK_OUT',
            'gatepass_id' => (int)$gatepass['id'],
            'gate_id' => (int)$device['gate_id'],
            'device_id' => (int)$device['device_id'],
            'guard_user_id' => $guardUserId,
        ];
    }

    private function deny(string $reason, ?array $gatepass = null, ?array $device = null): array
    {
        return [
            'decision' => 'DENY',
            'reason_code' => $reason,
            'action' => 'NONE',
            'gatepass_id' => $gatepass['id'] ?? null,
            'gate_id' => $device['gate_id'] ?? null,
            'device_id' => $device['device_id'] ?? null,
        ];
    }
}
