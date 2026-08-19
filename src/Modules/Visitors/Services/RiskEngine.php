<?php

namespace App\Modules\Visitors\Services;

class RiskEngine
{
    public function calculate(
        ?string $idNumber,
        ?string $phone,
        bool $isBlacklisted = false,
        int $previousVisits = 0
    ): int {

        $score = 0;

        if (!$idNumber) {
            $score += 30;
        }

        if (!$phone) {
            $score += 20;
        }

        if ($previousVisits === 0) {
            $score += 0;
        }

        if ($isBlacklisted) {
            $score += 50;
        }

        return min($score, 100);
    }
}