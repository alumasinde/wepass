<?php

declare(strict_types=1);

namespace App\Modules\Gatepass\Services;

/** Small application boundary for scheduled expiry processing. */
final class GatepassExpiryService
{
    public function __construct(private readonly GatepassStateService $states = new GatepassStateService()) {}

    public function run(int $batchSize = 200): int
    {
        return $this->states->expireDueGatepasses($batchSize);
    }
}
