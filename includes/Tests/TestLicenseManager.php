<?php
/**
 * Unit test for ENZO LicenseManager.
 * Tests license activation/deactivation logic.
 */
if (!defined('WP_TEST_DIR')) {
    define('WP_TEST_DIR', sys_get_temp_dir() . '/wordpress-tests-lib');
}

class TestLicenseManager extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option('enzo_license');
    }

    protected function tearDown(): void
    {
        delete_option('enzo_license');
        parent::tearDown();
    }

    public function test_status_returns_default_when_no_license(): void
    {
        $status = \ENZO\License\LicenseManager::status();

        $this->assertFalse($status['activated']);
        $this->assertFalse($status['valid']);
        $this->assertFalse($status['expired']);
        $this->assertFalse($status['grace']);
    }

    public function test_activate_fails_on_empty_key(): void
    {
        $result = \ENZO\License\LicenseManager::activate('');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('خالی', $result['message']);
    }

    public function test_deactivate_removes_option(): void
    {
        update_option('enzo_license', ['key' => 'test', 'activated' => true]);

        $result = \ENZO\License\LicenseManager::deactivate();

        $this->assertTrue($result['success']);
        $this->assertFalse(get_option('enzo_license', false));
    }

    public function test_fail_open_returns_true_when_not_activated(): void
    {
        $this->assertTrue(\ENZO\License\LicenseManager::failOpen());
    }
}