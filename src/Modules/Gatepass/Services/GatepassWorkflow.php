<?php

namespace App\Modules\Gatepass\Services;

use RuntimeException;

class GatepassWorkflow
{
    /**
     * Safe defaults for OUTBOUND types — used whenever a tenant
     * hasn't configured their own rules yet (Settings -> Gatepass
     * Rules). Not used at all for inbound types — see
     * inboundEligibility() below, which is intentionally fixed.
     */
    public const DEFAULT_RULES = [
        'checkout_statuses'           => ['approved', 'checked_in'],
        'checkin_statuses'            => ['checked_out'],
        'checkin_requires_returnable' => true,
    ];

    /**
     * Returns which state-transition actions are eligible for the given gatepass row.
     * Does not enforce type-level allowed_actions — that is handled upstream.
     *
     * @param  array $g      Must contain: status_code. Optionally: actual_in, actual_out,
     *                       is_returnable, direction ('outbound'|'inbound', defaults outbound).
     * @param  array $rules  Configurable eligibility rules for OUTBOUND types only
     *                       (Settings -> Gatepass Rules). Ignored entirely for inbound
     *                       types, which use a fixed sequence — see inboundEligibility().
     * @return array{checkin_eligible: bool, checkout_eligible: bool}
     */
    public static function eligibility(array $g, array $rules = []): array
    {
        if (empty($g['status_code'])) {
            throw new RuntimeException('Invalid gatepass state: missing status_code.');
        }

        $direction = strtolower($g['direction'] ?? 'outbound');

        if ($direction === 'inbound') {
            return self::inboundEligibility($g);
        }

        return self::outboundEligibility($g, $rules);
    }

    /**
     * OUTBOUND (the original, only model before inbound existed):
     * something leaves first (Check-Out), optionally comes back
     * later (Check-In) — never the other order. Fully configurable
     * via Settings -> Gatepass Rules.
     */
    private static function outboundEligibility(array $g, array $rules): array
    {
        $rules = array_merge(self::DEFAULT_RULES, $rules);

        $status     = strtolower($g['status_code']);
        $returnable = (int) ($g['is_returnable'] ?? 0) === 1;

        return [
            'checkin_eligible'  => self::checkinEligible($status, $returnable, $g, $rules),
            'checkout_eligible' => self::checkoutEligible($status, $g, $rules),
        ];
    }

    /**
     * INBOUND (contractor tools, a visitor's own laptop, anything
     * arriving on-site before it leaves again): Check-In here means
     * "has arrived," Check-Out means "has left again" — the reverse
     * order from outbound. Deliberately fixed, not routed through
     * the same configurable Gatepass Rules as outbound — the shape
     * of this flow doesn't vary the way outbound's does, and keeping
     * it fixed means there's no way to misconfigure it into the same
     * before-it-ever-arrived bug outbound had before that was fixed.
     */
    private static function inboundEligibility(array $g): array
    {
        $status = strtolower($g['status_code']);

        $checkinEligible = empty($g['actual_in']) && $status === 'approved';

        $checkoutEligible = empty($g['actual_out']) && $status === 'checked_in';

        return [
            'checkin_eligible'  => $checkinEligible,
            'checkout_eligible' => $checkoutEligible,
        ];
    }

    private static function checkinEligible(string $status, bool $returnable, array $g, array $rules): bool
    {
        if (!empty($g['actual_in'])) {
            return false;
        }

        // Check-in ("returning") only ever makes sense for a
        // returnable gatepass that's already in one of the
        // configured check-in-eligible statuses (checked_out, by
        // default) — never before that, and never for a non-
        // returnable gatepass at all. checkin_requires_returnable is
        // itself configurable, but defaults on for exactly this
        // reason — turning it off is a deliberate choice, not the
        // out-of-the-box behavior.
        if (!empty($rules['checkin_requires_returnable']) && !$returnable) {
            return false;
        }

        $eligibleStatuses = array_map('strtolower', $rules['checkin_statuses'] ?? []);
        return in_array($status, $eligibleStatuses, true);
    }

    private static function checkoutEligible(string $status, array $g, array $rules): bool
    {
        if (!empty($g['actual_out'])) {
            return false;
        }

        $eligibleStatuses = array_map('strtolower', $rules['checkout_statuses'] ?? []);
        return in_array($status, $eligibleStatuses, true);
    }
}
