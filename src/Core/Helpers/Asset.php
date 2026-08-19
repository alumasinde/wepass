<?php

namespace App\Core\Helpers;

class Asset
{
    public static function tenantLogo(?string $logo): string
    {
        $basePath = '/uploads/tenants/';
        $default  = '/assets/images/default-logo.png';

        if (empty($logo)) {
            return $default;
        }

        $logo = basename($logo);

        $fullPath = $_SERVER['DOCUMENT_ROOT'] . $basePath . $logo;

        if (!is_file($fullPath)) {
            return $default;
        }

        return $basePath . $logo;
    }
}