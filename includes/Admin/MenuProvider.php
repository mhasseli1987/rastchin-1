<?php
namespace ENZO\Admin;

use ENZO\Settings\SettingsService;
use ENZO\Security\SecretStore;
use ENZO\Logging\Logger;
use ENZO\Infrastructure\Http\WpHttpTransport;
use ENZO\Infrastructure\WpDb\Installer;
use ENZO\Infrastructure\WpDb\LogRepository;
use ENZO\Services\ContentService;
use ENZO\AI\AIProviderRegistry;

defined('ABSPATH') || exit;

class MenuProvider
{
    public static function register(): void
    {
        $slug = 'enzo-telegram-ai-admin';

        // Add parent menu.
        add_menu_page(
            'ENZO: AI Telegram Admin',
            'ENZO: AI Admin',
            'manage_options',
            $slug,
            [self::class, 'adminPage'],
            'dashicons-admin-site',
            110  // position after Settings
        );

        // 14 submenu items (UX_SPEC.md:40-55).
        $subs = [
            ['Generated', 'generated', 'manage_options', 'enzo-generated'],
            ['Content', 'content', 'manage_options', 'enzo-content'],
            ['Scheduler', 'scheduler', 'manage_options', 'enzo-scheduler'],
            ['Queue', 'queue', 'manage_options', 'enzo-queue'],
            ['AI Providers', 'ai-providers', 'manage_options', 'enzo-ai-providers'],
            ['AI Generate', 'ai-generate', 'manage_options', 'enzo-ai-generate'],
            ['Channels', 'channels', 'manage_options', 'enzo-channels'],
            ['Posts', 'posts', 'manage_options', 'enzo-posts'],
            ['Logs', 'logs', 'manage_options', 'enzo-logs'],
            // License submenu is registered by LicenseManager::registerMenu()
            // (classic form — works even without the SPA).
            ['Settings', 'settings', 'manage_options', 'enzo-settings'],
            ['Dashboard', 'dashboard', 'manage_options', 'enzo-dashboard'],
            ['Help', 'help', 'manage_options', 'enzo-help'],
        ];

        foreach ($subs as [$label, $page, $cap, $menu]) {
            add_submenu_page(
                $slug,
                $label,
                $label,
                $cap,
                $menu,
                [self::class, 'subpage']
            );
        }

        // Enqueue React SPA assets.
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets(string $hook): void
    {
        // Hook suffixes: 'toplevel_page_{slug}' for the parent menu and
        // '{parent}_page_{sub}' for subpages — both must load the SPA.
        if (strpos($hook, 'enzo-') !== 0 && strpos($hook, 'page_enzo-') === false) {
            return;
        }

        wp_enqueue_script(
            'enzo-admin-app',
            ENZO_URL . 'includes/Admin/js/admin-app.js',
            ['wp-element', 'wp-components', 'wp-i18n'],
            ENZO_VERSION,
            true
        );

        wp_localize_script('enzo-admin-app', 'ENZO_REST_URL', [
            'url'   => rest_url('enzo/v1/'),
            // wp_rest nonce — without it every REST call from the SPA answers 403.
            'nonce' => wp_create_nonce('wp_rest'),
            'version' => ENZO_VERSION,
        ]);

        wp_enqueue_style(
            'enzo-admin-app',
            ENZO_URL . 'includes/Admin/css/admin-style.css',
            [],
            ENZO_VERSION
        );
    }

    public static function adminPage(): void
    {
        echo '<div class="wrap"><div id="enzo-admin-root"></div></div>';
    }

    public static function subpage(): void
    {
        echo '<div class="wrap"><div id="enzo-admin-root"></div></div>';
    }
}