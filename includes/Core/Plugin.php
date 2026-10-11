<?php
namespace ENZO\Core;

use ENZO\Core\Container; // real namespace: Container lives in Core (not ENZO\\)
use ENZO\Admin\MenuProvider;
use ENZO\Admin\RestApi;
use ENZO\Security\SecretStore;
use ENZO\Logging\Logger;
use ENZO\Infrastructure\Http\WpHttpTransport;
use ENZO\Infrastructure\WpDb\Installer;
use ENZO\Infrastructure\WpDb\LogRepository;
use ENZO\Settings\SettingsRegistry;
use ENZO\Settings\SettingsService;
use ENZO\Core\SettingsRegistrar;
use ENZO\Services\ContentService;
use ENZO\AI\AIProviderRegistry;
use ENZO\AI\OpenAICompatibleProvider;
use ENZO\Telegram\BotApiTelegramProvider;
use ENZO\Contracts\Telegram\TelegramProviderInterface;
use ENZO\Contracts\AI\AIProviderInterface;
use ENZO\Contracts\Log\LoggerInterface;
use ENZO\Contracts\SecretStoreInterface;
use ENZO\Contracts\HttpClientInterface;
use ENZO\Infrastructure\WpDb\PostRepository;
use ENZO\Cron\Runner;
use ENZO\Cron\SchedulerAdapter;
use ENZO\License\LicenseManager;

defined('ABSPATH') || exit;

/**
 * Main plugin bootstrap (Composition Root, D-13).
 * Wires all services into Container and registers WP hooks.
 */
class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        $this->registerContainer();
        $this->registerHooks();
    }

    /**
     * Public so integration tests can rebuild the container after reset()
     * (e.g. to swap the HTTP transport for a mock).
     */
    public function registerContainer(): void
    {
        $container = Container::instance();

        // Infrastructure singletons.
        $container->singleton(HttpClientInterface::class, fn() => new WpHttpTransport());
        $container->singleton(LoggerInterface::class, fn($c) => new Logger($c->make(LogRepository::class)));
        $container->singleton(SecretStoreInterface::class, fn() => new SecretStore());
        $container->singleton(LogRepository::class, fn() => new LogRepository());

        // Settings.
        $container->singleton(SettingsRegistry::class, function () {
            $reg = new SettingsRegistry();
            SettingsRegistrar::register($reg);
            return $reg;
        });
        $container->singleton(SettingsService::class, fn($c) => new SettingsService($c->make(SettingsRegistry::class)));

        // AI Registry + default provider.
        $container->singleton(AIProviderRegistry::class, function ($c) {
            $reg = new AIProviderRegistry();
            $provider = new OpenAICompatibleProvider(
                $c->make(HttpClientInterface::class),
                $c->make(LoggerInterface::class)
            );
            $reg->register('openai_compatible', $provider, true);
            return $reg;
        });

        // Default AI provider behind the interface (registry's default()).
        $container->singleton(AIProviderInterface::class, fn($c) => $c->make(AIProviderRegistry::class)->default());

        // Telegram provider.
        $container->singleton(TelegramProviderInterface::class, fn($c) => 
            new BotApiTelegramProvider(
                $c->make(HttpClientInterface::class),
                $c->make(LoggerInterface::class)
            )
        );

        // Repositories.
        $container->singleton(PostRepository::class, fn() => new PostRepository());

        // Services.
        $container->singleton(ContentService::class, fn($c) =>
            new ContentService(
                $c->make(TelegramProviderInterface::class),
                $c->make(AIProviderRegistry::class)->default(),
                $c->make(LoggerInterface::class),
                $c->make(PostRepository::class)
            )
        );
    }

    private function registerHooks(): void
    {
        // Admin menu.
        add_action('admin_menu', [MenuProvider::class, 'register']);

        // REST API.
        RestApi::register();

        // Activation/deactivation/uninstall hooks are registered in the main
        // plugin file only (they were registered here too, where they are dead code).

        // Cron queue runner: 1-minute custom schedule + idempotent scheduling.
        add_filter('cron_schedules', static function (array $schedules): array {
            $schedules['enzo_minute'] = [
                'interval' => MINUTE_IN_SECONDS,
                'display'  => 'Every Minute (ENZO)',
            ];
            return $schedules;
        });
        add_action(Runner::HOOK, [Runner::class, 'handle']);
        add_action('init', [Runner::class, 'ensureScheduled']);

        // Scheduler adapter: Action Scheduler preferred, WP-Cron fallback.
        SchedulerAdapter::register();

        // License submenu (LicenseManager::adminPage was hooked straight to
        // admin_menu before, which echoed HTML during menu registration).
        add_action('admin_menu', [LicenseManager::class, 'registerMenu']);

        // License status check on init.
        add_action('init', [LicenseManager::class, 'maybeCheckStatus']);

        // Phase 15: update/version check + secure download gate (fail-safe).
        \ENZO\License\Updater::register();

        // Assets.
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // Text domain.
        add_action('init', function (): void {
            load_plugin_textdomain('enzo-telegram-ai-admin', false, dirname(plugin_basename(ENZO_FILE)) . '/languages');
        });
    }

    public function enqueueAssets(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'enzo') === false) {
            return;
        }

        // Only load assets when the files actually exist (avoids 404s on MVP skeleton).
        $css = ENZO_DIR . 'assets/css/admin.css';
        $js = ENZO_DIR . 'assets/js/admin.js';
        if (is_file($css)) {
            wp_enqueue_style('enzo-admin', ENZO_URL . 'assets/css/admin.css', [], ENZO_VERSION);
        }
        if (is_file($js)) {
            wp_enqueue_script('enzo-admin', ENZO_URL . 'assets/js/admin.js', ['wp-element', 'wp-api-fetch', 'wp-components'], ENZO_VERSION, true);

            // Localize REST root + nonce (only when the handle exists).
            wp_localize_script('enzo-admin', 'enzoRest', [
                'root' => rest_url('enzo/v1'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        }
    }

    public static function activate(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $installer = new Installer();
        $installer->install();

        // One-time backfill for installs that still carry enzo_post CPT drafts
        // from the earlier (conflicting) storage model.
        self::migrateCptDraftsToTable();

        Runner::ensureScheduled();
    }

    public static function deactivate(): void
    {
        // Clear scheduled cron events.
        wp_clear_scheduled_hook(Runner::HOOK);
    }

    public static function uninstall(): void
    {
        // Remove cron.
        wp_clear_scheduled_hook(Runner::HOOK);

        // Delete tables and options.
        $installer = new Installer();
        $installer->uninstall();

        // Remove leftover CPT content from the old storage model.
        global $wpdb;
        $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'enzo_post'");
        if ($ids) {
            $in = implode(',', array_map('intval', $ids));
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($in)");
            $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'enzo_post'");
        }
    }

    /**
     * Copy legacy enzo_post CPT drafts into enzo_posts (once, guarded by an option).
     * Reconciles the two conflicting post models instead of silently dropping data.
     */
    private static function migrateCptDraftsToTable(): void
    {
        if (get_option('enzo_cpt_migrated')) {
            return;
        }

        $posts = get_posts([
            'post_type'      => 'enzo_post',
            'post_status'    => ['draft', 'pending', 'private'],
            'posts_per_page' => 500,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        if ($posts) {
            $repo = new \ENZO\Infrastructure\WpDb\PostRepository();
            foreach ($posts as $p) {
                $repo->insert([
                    'title'      => $p->post_title,
                    'body'       => (string) get_post_meta($p->ID, 'post_text', true) ?: $p->post_content,
                    'channel_id' => (int) get_post_meta($p->ID, 'post_channel_id', true),
                    'status'     => 'draft',
                ]);
                wp_delete_post($p->ID, true);
            }
        }

        update_option('enzo_cpt_migrated', 1, false);
    }

}