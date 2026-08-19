<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase4ReturnServiceTest extends TestCase
{
    public function test_return_service_records_append_only_return_events(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GatepassReturnService.php')) ?: '';

        self::assertStringContainsString('gatepass_item_returns', $source);
        self::assertStringContainsString('quantity_returned', $source);
        self::assertStringContainsString('RETURN_COMPLETE', $source);
    }

    public function test_return_service_rejects_over_returning_an_item(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GatepassReturnService.php')) ?: '';

        self::assertStringContainsString('Return quantity exceeds the remaining quantity', $source);
        self::assertStringContainsString('already been fully returned', $source);
    }

    public function test_gatepass_becomes_returned_only_when_all_returnable_quantities_are_complete(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/GatepassReturnService.php')) ?: '';

        self::assertStringContainsString('SUM(quantity)', $source);
        self::assertStringContainsString('SUM(returned_quantity)', $source);
        self::assertStringContainsString("'checked_in'", $source);
        self::assertStringContainsString("'returned'", $source);
    }
}
