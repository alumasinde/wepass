<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Settings\Services\TenantSettingService;

/**
 * ThemeSettingController — lets each tenant pick their own header
 * background, sidebar background/text, and primary "theme" color
 * (which drives buttons, links, and active states throughout the
 * app — see public_html/assets/css/token.css). Stored per-tenant in
 * tenant_settings under the 'theme' key; rendered via
 * theme_css_vars() (bootstrap/helpers.php) into every authenticated
 * page.
 */
class ThemeSettingController extends Controller
{
    private const DEFAULTS = [

    // Brand
    'primary_color'   => '#1E3A5F',
    'secondary_color' => '#64748B',

    // Layout
    'sidebar_bg'      => '#1E293B',
    'sidebar_text'    => '#F8FAFC',
    'header_bg'       => '#FFFFFF',
    'page_bg'         => '#F8FAFC',

];

    private TenantSettingService $settings;

    public function __construct()
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }

        $this->settings = new TenantSettingService();
    }

    public function index()
    {
        $theme = array_merge(self::DEFAULTS, $this->settings->get('theme', []));

        return $this->view('Settings::theme', [
            'title'    => 'Theme',
            'theme'    => $theme,
            'defaults' => self::DEFAULTS,
        ]);
    }

    public function update(Request $request)
    {
        $fields = [
    'primary_color',
    'secondary_color',
    'sidebar_bg',
    'sidebar_text',
    'header_bg',
    'page_bg',
];
        $theme = [];

foreach ($fields as $field) {

    $value = strtolower(trim((string) $request->input($field, '')));

    if (!preg_match('/^#[0-9a-f]{6}$/', $value)) {
        $value = self::DEFAULTS[$field];
    }

    $theme[$field] = $value;
}

        $this->settings->set('theme', $theme);

		flash('Theme updated successfully.', 'success');
        return $this->redirect('/settings/theme');
    }

    public function reset()
    {
        $this->settings->delete('theme');

flash('Theme reset to the default appearance.', 'success');
		return $this->redirect('/settings/theme');
    }
}
