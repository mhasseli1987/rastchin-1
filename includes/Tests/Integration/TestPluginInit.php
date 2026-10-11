<?php
/**
 * Integration Test: Plugin Initialization & DI Container
 * Tests that the plugin bootstraps correctly, registers all services,
 * and the DI container resolves dependencies properly.
 */
require_once __DIR__ . '/bootstrap.php';

class TestPluginInit extends IntegrationTestCase
{
    public function test_container_is_singleton(): void
    {
        $c1 = \ENZO\Core\Container::instance();
        $c2 = \ENZO\Core\Container::instance();
        $this->assertSame($c1, $c2);
    }

    public function test_container_resolves_core_services(): void
    {
        $container = $this->container;

        // Settings
        $settings = $container->make(\ENZO\Settings\SettingsService::class);
        $this->assertInstanceOf(\ENZO\Settings\SettingsService::class, $settings);

        $registry = $container->make(\ENZO\Settings\SettingsRegistry::class);
        $this->assertInstanceOf(\ENZO\Settings\SettingsRegistry::class, $registry);

        // Security
        $secrets = $container->make(\ENZO\Contracts\SecretStoreInterface::class);
        $this->assertInstanceOf(\ENZO\Security\SecretStore::class, $secrets);

        $ssrf = $container->make(\ENZO\Security\SsrfGate::class);
        $this->assertInstanceOf(\ENZO\Security\SsrfGate::class, $ssrf);

        // Logging
        $logger = $container->make(\ENZO\Contracts\Log\LoggerInterface::class);
        $this->assertInstanceOf(\ENZO\Logging\Logger::class, $logger);

        // HTTP
        $http = $container->make(\ENZO\Contracts\HttpClientInterface::class);
        $this->assertInstanceOf(\ENZO\Contracts\HttpClientInterface::class, $http);

        // AI
        $aiRegistry = $container->make(\ENZO\AI\AIProviderRegistry::class);
        $this->assertInstanceOf(\ENZO\AI\AIProviderRegistry::class, $aiRegistry);

        $aiProvider = $container->make(\ENZO\Contracts\AI\AIProviderInterface::class);
        $this->assertInstanceOf(\ENZO\AI\OpenAICompatibleProvider::class, $aiProvider);

        // Telegram
        $telegram = $container->make(\ENZO\Contracts\Telegram\TelegramProviderInterface::class);
        $this->assertInstanceOf(\ENZO\Telegram\BotApiTelegramProvider::class, $telegram);

        // Services
        $content = $container->make(\ENZO\Services\ContentService::class);
        $this->assertInstanceOf(\ENZO\Services\ContentService::class, $content);

        // DB
        $postRepo = $container->make(\ENZO\Infrastructure\WpDb\PostRepository::class);
        $this->assertInstanceOf(\ENZO\Infrastructure\WpDb\PostRepository::class, $postRepo);

        $logRepo = $container->make(\ENZO\Infrastructure\WpDb\LogRepository::class);
        $this->assertInstanceOf(\ENZO\Infrastructure\WpDb\LogRepository::class, $logRepo);

        // Cron
        $runner = $container->make(\ENZO\Cron\Runner::class);
        $this->assertInstanceOf(\ENZO\Cron\Runner::class, $runner);

        $scheduler = $container->make(\ENZO\Cron\SchedulerAdapter::class);
        $this->assertInstanceOf(\ENZO\Cron\SchedulerAdapter::class, $scheduler);

        // License
        $license = $container->make(\ENZO\License\LicenseManager::class);
        $this->assertInstanceOf(\ENZO\License\LicenseManager::class, $license);
    }

    public function test_container_returns_same_instance_for_singletons(): void
    {
        $logger1 = $this->container->make(\ENZO\Contracts\Log\LoggerInterface::class);
        $logger2 = $this->container->make(\ENZO\Contracts\Log\LoggerInterface::class);
        $this->assertSame($logger1, $logger2);

        $secrets1 = $this->container->make(\ENZO\Contracts\SecretStoreInterface::class);
        $secrets2 = $this->container->make(\ENZO\Contracts\SecretStoreInterface::class);
        $this->assertSame($secrets1, $secrets2);
    }

    public function test_plugin_hooks_registered(): void
    {
        // Check that admin_menu hook has our callback
        $hooks = $GLOBALS['wp_filter']['admin_menu']->callbacks ?? [];
        $found = false;
        foreach ($hooks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (isset($callback['function']) &&
                    is_array($callback['function']) &&
                    $callback['function'][0] === \ENZO\Admin\MenuProvider::class) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'MenuProvider::register not hooked to admin_menu');
    }

    public function test_rest_api_routes_registered(): void
    {
        // Ensure REST routes are registered
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/enzo/v1/telegram/connect', $routes);
        $this->assertArrayHasKey('/enzo/v1/channels', $routes);
        $this->assertArrayHasKey('/enzo/v1/ai/providers', $routes);
        $this->assertArrayHasKey('/enzo/v1/ai/generate', $routes);
        $this->assertArrayHasKey('/enzo/v1/posts', $routes);
        $this->assertArrayHasKey('/enzo/v1/queue', $routes);
        $this->assertArrayHasKey('/enzo/v1/logs', $routes);
        $this->assertArrayHasKey('/enzo/v1/dashboard', $routes);
        $this->assertArrayHasKey('/enzo/v1/settings', $routes);
        $this->assertArrayHasKey('/enzo/v1/license/activate', $routes);
    }

    public function test_cron_schedule_enzo_minute_exists(): void
    {
        $schedules = wp_get_schedules();
        $this->assertArrayHasKey('enzo_minute', $schedules);
        $this->assertEquals(MINUTE_IN_SECONDS, $schedules['enzo_minute']['interval']);
    }

    public function test_license_manager_registered(): void
    {
        $license = $this->container->make(\ENZO\License\LicenseManager::class);
        $this->assertInstanceOf(\ENZO\License\LicenseManager::class, $license);

        // Check admin page hook
        $hooks = $GLOBALS['wp_filter']['admin_menu']->callbacks ?? [];
        $found = false;
        foreach ($hooks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (isset($callback['function']) &&
                    is_array($callback['function']) &&
                    $callback['function'][0] === \ENZO\License\LicenseManager::class) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'LicenseManager::adminPage not hooked to admin_menu');
    }

    public function test_scheduler_adapter_registered(): void
    {
        $scheduler = $this->container->make(\ENZO\Cron\SchedulerAdapter::class);
        $this->assertInstanceOf(\ENZO\Cron\SchedulerAdapter::class, $scheduler);

        // Check init hook
        $hooks = $GLOBALS['wp_filter']['init']->callbacks ?? [];
        $found = false;
        foreach ($hooks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (isset($callback['function']) &&
                    is_array($callback['function']) &&
                    $callback['function'][0] === \ENZO\Cron\SchedulerAdapter::class) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'SchedulerAdapter::maybeSwitchToActionScheduler not hooked to init');
    }

    public function test_settings_loaded(): void
    {
        $registry = $this->container->make(\ENZO\Settings\SettingsRegistry::class);
        $definitions = $registry->definitions();

        // Should have at least these core settings
        $this->assertArrayHasKey('enzo_default_tone', $definitions);
        $this->assertArrayHasKey('enzo_default_language', $definitions);
        $this->assertArrayHasKey('enzo_license_key', $definitions);
        $this->assertArrayHasKey('enzo_log_retention_days', $definitions);
    }

    public function test_secret_store_encryption_key_derivation(): void
    {
        $store = $this->container->make(\ENZO\Contracts\SecretStoreInterface::class);

        // Test that we can store and retrieve a secret
        $store->set('test_key', 'test_value_123');
        $value = $store->get('test_key');
        $this->assertEquals('test_value_123', $value);

        // Test has()
        $this->assertTrue($store->has('test_key'));
        $this->assertFalse($store->has('nonexistent'));

        // Test delete()
        $store->delete('test_key');
        $this->assertNull($store->get('test_key'));
    }
}