<?php

namespace App\Modules\Gatepass\DTOs;

class GatepassDTO
{
    public function __construct(
        public string $statusCode,
        public ?string $actualIn,
        public ?string $actualOut,
        public bool $isReturnable
    ) {}
}