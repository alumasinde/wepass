<?php

namespace App\Modules\Settings\DTOs;

class DepartmentDTO
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $name,
        public string $code,
        public bool $isActive,
        public ?string $createdAt = null
    ) {}
}