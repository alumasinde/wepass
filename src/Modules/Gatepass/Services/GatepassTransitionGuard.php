<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Phase 4 transition boundary. Keeps legal state transitions explicit
 * and fail-closed before persistence is attempted.
 */
final class GatepassTransitionGuard
{
    private const TERMINAL = ['rejected', 'cancelled', 'expired', 'returned'];

    /** @var array<string, string[]> */
    private const ALLOWED = [
        'pending' => ['submitted', 'cancelled', 'expired'],
        'submitted' => ['approved', 'rejected', 'cancelled', 'expired'],
        'approved' => ['checked_out', 'checked_in', 'cancelled', 'expired'],
        'checked_out' => ['checked_in'],
        'checked_in' => ['checked_out', 'returned'],
    ];

    public static function assert(string $from, string $to, string $transition): void
    {
        $from = strtolower(trim($from));
        $to = strtolower(trim($to));
        $transition = strtoupper(trim($transition));

        if ($from === '' || $to === '' || $transition === '') {
            throw new InvalidArgumentException('Transition state and code are required.');
        }

        if (in_array($from, self::TERMINAL, true)) {
            throw new RuntimeException('Terminal gatepass states cannot transition.');
        }

        if (!in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new RuntimeException("Invalid gatepass transition: {$from} -> {$to}.");
        }
    }

    public static function isTerminal(string $status): bool
    {
        return in_array(strtolower(trim($status)), self::TERMINAL, true);
    }
}
