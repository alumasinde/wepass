<?php

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Drop this straight inside a <form>:  <?= csrf_field() ?>
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Repopulate a form field after a failed submission. Controllers
     * that redirect back on validation failure should set
     * $_SESSION['old'] = $request->all() before redirecting.
     */
    function old(string $key, string $default = ''): string
    {
        return e($_SESSION['old'][$key] ?? $default);
    }
}

if (!function_exists('flash')) {
    /**
     * Set a one-time flash message, read on the next request by
     * layouts/app.php and layouts/guest.php.
     */
    function flash(string $message, string $type = 'info'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
}

if (!function_exists('redirect_with')) {
    /**
     * Flash a message and redirect in one call — replaces the
     * repeated "set flash, header Location, exit" block duplicated
     * across LoginController, PasswordController, etc.
     */
    function redirect_with(string $url, string $message, string $type = 'info'): never
    {
        flash($message, $type);
        header("Location: {$url}");
        exit;
    }
}

if (!function_exists('asset')) {
    /**
     * Cache-busted asset URL: asset('css/app.css') -> /assets/css/app.css?v=<mtime>
     */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $full = base_path('public/assets/' . $path);

        $version = is_file($full) ? filemtime($full) : time();

        return '/assets/' . $path . '?v=' . $version;
    }
}

if (!function_exists('can')) {
    /**
     * Permission check helper for use directly in views:
     *   <?php if (can('gatepasses.approve')): ?> ... <?php endif; ?>
     * Reads the permission map already loaded into the session by
     * App\Core\Permission::loadForUser() at login — no DB hit here.
     */
    function can(string $permission): bool
    {
        if (!empty($_SESSION['is_super_admin'])) {
            return true;
        }

        return isset($_SESSION['permissions'][$permission]);
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $datetime, string $format = 'd M Y, H:i'): string
    {
        if (empty($datetime)) {
            return '—';
        }

        try {
            return (new DateTime($datetime))->format($format);
        } catch (\Throwable) {
            return '—';
        }
    }
}

if (!function_exists('str_limit')) {
    function str_limit(?string $value, int $limit = 80, string $end = '…'): string
    {
        $value = $value ?? '';

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit) . $end;
    }
}

if (!function_exists('active_route')) {
    /**
     * CSS class helper for nav links: <a class="<?= active_route('/gatepasses') ?>">
     */
    function active_route(string $path): string
    {
        $current = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/') ?: '/';
        $path    = rtrim($path, '/') ?: '/';

        return str_starts_with($current, $path) ? 'active' : '';
    }
}

if (!function_exists('theme_css_vars')) {
    function theme_css_vars(): string
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $defaults = [

            // Brand
            'primary_color'      => '#1E3A5F',
            'secondary_color'    => '#64748B',

            // Layout
            'sidebar_bg'         => '#1E293B',
            'sidebar_text'       => '#F8FAFC',
            'header_bg'          => '#FFFFFF',
            'page_bg'            => '#F8FAFC',

            // Cards
            'card_bg'            => '#FFFFFF',
            'card_border'        => '#E2E8F0',

            // Typography
            'text_color'         => '#0F172A',
            'text_muted'         => '#64748B',

            // Status
            'success_color'      => '#16A34A',
            'warning_color'      => '#F59E0B',
            'danger_color'       => '#DC2626',
            'info_color'         => '#0EA5E9',

            // Navigation
            'sidebar_active'     => '#2563EB',
            'sidebar_hover'      => '#334155',

            // Links
            'link_color'         => '#2563EB',
            'link_hover'         => '#1D4ED8',

            // Forms
            'input_bg'           => '#FFFFFF',
            'input_border'       => '#CBD5E1',
            'input_focus'        => '#2563EB',

            // Tables
            'table_header_bg'    => '#F8FAFC',
            'table_row_hover'    => '#F1F5F9',
        ];

        $theme = $defaults;

        try {
            $service = new \App\Modules\Settings\Services\TenantSettingService();
            $theme = array_merge($defaults, $service->get('theme', []));
        } catch (\Throwable $e) {
            // Ignore and use defaults.
        }

        $hex = static function ($value, string $fallback): string {
            $value = (string) $value;

            return preg_match('/^#[0-9A-Fa-f]{6}$/', $value)
                ? strtolower($value)
                : strtolower($fallback);
        };

        $primary        = $hex($theme['primary_color'] ?? null, $defaults['primary_color']);
        $secondary      = $hex($theme['secondary_color'] ?? null, $defaults['secondary_color']);

        $sidebarBg      = $hex($theme['sidebar_bg'] ?? null, $defaults['sidebar_bg']);
        $sidebarText    = $hex($theme['sidebar_text'] ?? null, $defaults['sidebar_text']);
        $headerBg       = $hex($theme['header_bg'] ?? null, $defaults['header_bg']);

        $pageBg         = $hex($theme['page_bg'] ?? null, $defaults['page_bg']);

        $cardBg         = $hex($theme['card_bg'] ?? null, $defaults['card_bg']);
        $cardBorder     = $hex($theme['card_border'] ?? null, $defaults['card_border']);

        $textColor      = $hex($theme['text_color'] ?? null, $defaults['text_color']);
        $textMuted      = $hex($theme['text_muted'] ?? null, $defaults['text_muted']);

        $success        = $hex($theme['success_color'] ?? null, $defaults['success_color']);
        $warning        = $hex($theme['warning_color'] ?? null, $defaults['warning_color']);
        $danger         = $hex($theme['danger_color'] ?? null, $defaults['danger_color']);
        $info           = $hex($theme['info_color'] ?? null, $defaults['info_color']);

        $sidebarActive  = $hex($theme['sidebar_active'] ?? null, $defaults['sidebar_active']);
        $sidebarHoverBg = $hex($theme['sidebar_hover'] ?? null, $defaults['sidebar_hover']);

        $linkColor      = $hex($theme['link_color'] ?? null, $defaults['link_color']);
        $linkHover      = $hex($theme['link_hover'] ?? null, $defaults['link_hover']);

        $inputBg        = $hex($theme['input_bg'] ?? null, $defaults['input_bg']);
        $inputBorder    = $hex($theme['input_border'] ?? null, $defaults['input_border']);
        $inputFocus     = $hex($theme['input_focus'] ?? null, $defaults['input_focus']);

        $tableHeader    = $hex($theme['table_header_bg'] ?? null, $defaults['table_header_bg']);
        $tableHover     = $hex($theme['table_row_hover'] ?? null, $defaults['table_row_hover']);

        $primaryDark    = \App\Core\Helpers\ColorHelper::darken($primary, 0.15);
        $primaryLight   = \App\Core\Helpers\ColorHelper::lighten($primary, 0.92);
        $primaryHover   = \App\Core\Helpers\ColorHelper::darken($primary, 0.75);
        $primaryRing    = \App\Core\Helpers\ColorHelper::toRgba($primary, 0.18);

        $sidebarMuted   = \App\Core\Helpers\ColorHelper::toRgba($sidebarText, 0.50);

        $css = ':root{'

            // Brand
            . "--color-primary:{$primary};"
            . "--color-primary-dark:{$primaryDark};"
            . "--color-primary-light:{$primaryLight};"
            . "--color-primary-hover:{$primaryHover};"
            . "--color-primary-ring:{$primaryRing};"
            . "--color-secondary:{$secondary};"

            // Layout
            . "--color-sidebar-bg:{$sidebarBg};"
            . "--color-sidebar-text:{$sidebarText};"
            . "--color-sidebar-muted:{$sidebarMuted};"
            . "--color-sidebar-hover:{$sidebarHoverBg};"
            . "--color-sidebar-active:{$sidebarActive};"

            . "--color-header-bg:{$headerBg};"
            . "--color-page-bg:{$pageBg};"

            // Cards
            . "--color-card-bg:{$cardBg};"
            . "--color-card-border:{$cardBorder};"

            // Typography
            . "--color-text:{$textColor};"
            . "--color-text-muted:{$textMuted};"

            // Status
            . "--color-success:{$success};"
            . "--color-warning:{$warning};"
            . "--color-danger:{$danger};"
            . "--color-info:{$info};"

            // Links
            . "--color-link:{$linkColor};"
            . "--color-link-hover:{$linkHover};"

            // Forms
            . "--color-input-bg:{$inputBg};"
            . "--color-input-border:{$inputBorder};"
            . "--color-input-focus:{$inputFocus};"

            // Tables
            . "--color-table-header:{$tableHeader};"
            . "--color-table-row-hover:{$tableHover};"

            // Radius
            . "--radius-sm:6px;"
            . "--radius-md:10px;"
            . "--radius-lg:14px;"

            // Shadows
            . "--shadow-sm:0 1px 2px rgba(15,23,42,.05);"
            . "--shadow-md:0 4px 10px rgba(15,23,42,.08);"
            . "--shadow-lg:0 10px 25px rgba(15,23,42,.12);"

            // Animation
            . "--transition:all .2s ease;"

            . '}';

        $cached = '<style id="tenant-theme-overrides">' . $css . '</style>';

        return $cached;
    }
}

if (!function_exists('app_version')) {
    /**
     * Reads the "version" field from composer.json — single source
     * of truth for which build is deployed. Useful once you're
     * running the same codebase across several tenant subdomains
     * and need to know which one is on which version. Falls back to
     * 'dev' rather than failing if composer.json is ever missing or
     * malformed.
     */
    function app_version(): string
    {
        static $version = null;
        if ($version !== null) {
            return $version;
        }

        $path = base_path('composer.json');
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data) && !empty($data['version'])) {
                $version = (string) $data['version'];
                return $version;
            }
        }

        $version = 'dev';
        return $version;
    }
}

if (!function_exists('csp_nonce')) {
    /**
     * The per-request CSP nonce generated in bootstrap/app.php.
     * Every inline <script> block in a view must carry
     * nonce="<?= csp_nonce() ?>" or the browser will refuse to run
     * it under the script-src policy set there. Falls back to
     * generating one on the spot if called before bootstrap ran
     * (shouldn't normally happen) so a view never fatals over this.
     */
    function csp_nonce(): string
    {
        if (empty($GLOBALS['csp_nonce'])) {
            $GLOBALS['csp_nonce'] = bin2hex(random_bytes(16));
        }

        return $GLOBALS['csp_nonce'];
    }
}
