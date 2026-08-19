<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ScanExportSecurityTest extends TestCase
{
    public function test_export_is_bounded_and_streamed(): void
    {
        $sql = file_get_contents(base_path('src/Modules/Gatepass/Services/ScanExportService.php')) ?: '';
        self::assertStringContainsString('min($maxRows, 10000)', $sql);
        self::assertStringContainsString("fopen('php://output', 'wb')", $sql);
    }

    public function test_export_route_requires_scans_export(): void
    {
        $routes = file_get_contents(base_path('config/route_permissions.php')) ?: '';
        self::assertStringContainsString("scan-history/export\\.csv$#' => ['scans.export']", $routes);
    }

    public function test_export_is_audited(): void
    {
        $controller = file_get_contents(base_path('src/Modules/Gatepass/Controllers/ScanExportController.php')) ?: '';
        self::assertStringContainsString("Audit::log('gate_scan.export'", $controller);
    }
}
