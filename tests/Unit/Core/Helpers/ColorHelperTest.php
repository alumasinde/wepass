<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Helpers;

use App\Core\Helpers\ColorHelper;
use PHPUnit\Framework\TestCase;

/**
 * Every expected value below was computed independently (not just
 * "run it and paste the output") before being written here, so a
 * bug in ColorHelper itself would actually fail these rather than
 * the test having been written to match whatever the code happened
 * to produce.
 */
final class ColorHelperTest extends TestCase
{
    public function test_darken_reduces_each_channel_proportionally(): void
    {
        // #2563eb = rgb(37, 99, 235); darken 15%: 37*0.85=31.45→31,
        // 99*0.85=84.15→84, 235*0.85=199.75→200 (rounds up)
        $this->assertSame('#1f54c8', ColorHelper::darken('#2563eb', 0.15));
    }

    public function test_darken_at_75_percent_used_for_hover_shade(): void
    {
        $this->assertSame('#09193b', ColorHelper::darken('#2563eb', 0.75));
    }

    public function test_lighten_moves_each_channel_toward_white(): void
    {
        $this->assertSame('#eef3fd', ColorHelper::lighten('#2563eb', 0.92));
    }

    public function test_darken_white_by_half_is_mid_grey(): void
    {
        $this->assertSame('#808080', ColorHelper::darken('#ffffff', 0.5));
    }

    public function test_lighten_black_by_half_is_mid_grey(): void
    {
        $this->assertSame('#808080', ColorHelper::lighten('#000000', 0.5));
    }

    public function test_darken_black_stays_black(): void
    {
        $this->assertSame('#000000', ColorHelper::darken('#000000', 0.5));
    }

    public function test_lighten_white_stays_white(): void
    {
        $this->assertSame('#ffffff', ColorHelper::lighten('#ffffff', 0.5));
    }

    public function test_darken_by_zero_percent_is_unchanged(): void
    {
        $this->assertSame('#2563eb', ColorHelper::darken('#2563eb', 0.0));
    }

    public function test_darken_by_100_percent_is_black(): void
    {
        $this->assertSame('#000000', ColorHelper::darken('#2563eb', 1.0));
    }

    public function test_to_rgba_preserves_channels_and_alpha(): void
    {
        $this->assertSame('rgba(37, 99, 235, 0.18)', ColorHelper::toRgba('#2563eb', 0.18));
    }

    public function test_to_rgba_clamps_alpha_above_one(): void
    {
        $this->assertSame('rgba(37, 99, 235, 1)', ColorHelper::toRgba('#2563eb', 5.0));
    }

    public function test_to_rgba_clamps_alpha_below_zero(): void
    {
        $this->assertSame('rgba(37, 99, 235, 0)', ColorHelper::toRgba('#2563eb', -5.0));
    }

    /**
     * This is the security-relevant case: theme_css_vars() relies on
     * ColorHelper falling back safely rather than producing garbage
     * (or worse, passing malformed input straight through) when
     * fed something that isn't a clean 6-digit hex string.
     */
    public function test_invalid_hex_falls_back_instead_of_crashing(): void
    {
        $this->assertSame('#2563eb', ColorHelper::darken('not-a-color', 0.0));
        $this->assertSame('#2563eb', ColorHelper::lighten('<script>', 0.0));
        $this->assertSame('rgba(37, 99, 235, 1)', ColorHelper::toRgba('', 1.0));
    }

    public function test_accepts_hex_without_leading_hash(): void
    {
        $this->assertSame('rgba(37, 99, 235, 1)', ColorHelper::toRgba('2563eb', 1.0));
    }
}
