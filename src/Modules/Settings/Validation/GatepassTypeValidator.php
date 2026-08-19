<?php

namespace App\Modules\Settings\Validation;


class GatepassTypeValidator
{
    public static function validateActions(bool $checkin, bool $checkout): void
    {
        // Both false is valid — a type may have no actions permitted
    }
    public static function validateCreate(string $name, string $code, bool $checkin, bool $checkout): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Name is required');
        }

        self::validateActions($checkin, $checkout);
    }

    public static function validateUpdate(int $id, string $name, string $code, bool $checkin, bool $checkout): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid ID');
        }

        if (trim($name) === '') {
            throw new \InvalidArgumentException('Name is required');
        }

        self::validateActions($checkin, $checkout);
    }
}