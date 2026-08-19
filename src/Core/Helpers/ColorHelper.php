<?php

namespace App\Core\Helpers;

/**
 * ColorHelper — small, dependency-free hex color math used to derive
 * a full set of theme shades (dark/light/rgba) from a single
 * admin-picked color, so a tenant only has to choose one swatch per
 * concern (e.g. "theme color") instead of five separate ones.
 *
 * Every public method expects a strict 6-digit hex string (#rrggbb).
 * This class does NOT sanitize for CSS-injection safety — that's the
 * caller's job (see theme_css_vars() in bootstrap/helpers.php, which
 * validates every value against a hex pattern before it's ever
 * interpolated into a <style> block). If bad input somehow reaches
 * here anyway, toRgb() falls back to a safe default rather than
 * crashing page rendering.
 */
final class ColorHelper
{
    private const FALLBACK_HEX = '2563eb';

    public static function darken(string $hex, float $percent): string
    {
        [$r, $g, $b] = self::toRgb($hex);
        $percent = max(0, min(1, $percent));

        return self::toHex(
            (int) round($r * (1 - $percent)),
            (int) round($g * (1 - $percent)),
            (int) round($b * (1 - $percent))
        );
    }

    public static function lighten(string $hex, float $percent): string
    {
        [$r, $g, $b] = self::toRgb($hex);
        $percent = max(0, min(1, $percent));

        return self::toHex(
            (int) round($r + (255 - $r) * $percent),
            (int) round($g + (255 - $g) * $percent),
            (int) round($b + (255 - $b) * $percent)
        );
    }

    public static function toRgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::toRgb($hex);
        $alpha = max(0, min(1, $alpha));

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    private static function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            $hex = self::FALLBACK_HEX;
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function toHex(int $r, int $g, int $b): string
    {
        $clamp = fn (int $v): int => max(0, min(255, $v));

        return sprintf('#%02x%02x%02x', $clamp($r), $clamp($g), $clamp($b));
    }
}
