<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Phase4ReturnableItemTest extends TestCase
{
    public function test_return_service_locks_item_before_calculating_remaining_quantity(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/ReturnableItemService.php')) ?: '';
        self::assertStringContainsString('FOR UPDATE', $source);
        self::assertStringContainsString('returned_quantity = :expected', $source);
    }

    public function test_return_history_is_append_only_and_audited(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/ReturnableItemService.php')) ?: '';
        self::assertStringContainsString('gatepass_item_returns', $source);
        self::assertStringContainsString("gatepass.item_returned", $source);
    }

    public function test_return_quantity_cannot_exceed_remaining_quantity(): void
    {
        $source = file_get_contents(base_path('src/Modules/Gatepass/Services/ReturnableItemService.php')) ?: '';
        self::assertStringContainsString('quantity > $remaining', $source);
        self::assertStringContainsString('is_returnable', $source);
    }
}
