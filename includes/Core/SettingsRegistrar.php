<?php
namespace ENZO\Core;

use ENZO\Settings\SettingsRegistry;

defined('ABSPATH') || exit;

/**
 * Registers all plugin settings into the central registry.
 * Called once during boot.
 */
class SettingsRegistrar
{
    public static function register(SettingsRegistry $registry): void
    {
        $registry->register('enzo_platform', [
            'default'   => 'telegram',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'telegram',
            'description' => 'Platform for bot communication (telegram or bale).',
        ]);
        $registry->register('enzo_default_tone', [
            'default'   => 'friendly',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'ai',
            'description' => 'Default tone for AI-generated content.',
        ]);

        $registry->register('enzo_default_language', [
            'default'   => 'fa',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'ai',
            'description' => 'Default output language (fa=Persian, en=English).',
        ]);

        $registry->register('enzo_post_template', [
            'default'   => '',
            'type'      => 'textarea',
            'sanitize'  => 'sanitize_textarea_field',
            'group'     => 'ai',
            'description' => 'Optional custom template for AI content generation.',
        ]);

        $registry->register('enzo_auto_preview', [
            'default'   => '1',
            'type'      => 'boolean',
            'sanitize'  => 'rest_sanitize_boolean',
            'group'     => 'workflow',
            'description' => 'Show Telegram preview after generation.',
        ]);

        $registry->register('enzo_log_retention_days', [
            'default'   => 30,
            'type'      => 'integer',
            'sanitize'  => 'absint',
            'group'     => 'system',
            'description' => 'Log retention in days.',
        ]);

        $registry->register('enzo_license_key', [
            'default'   => '',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'license',
            'description' => 'License key (stored encrypted).',
        ]);

        $registry->register('enzo_license_site', [
            'default'   => '',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'license',
            'description' => 'Site URL registered with license.',
        ]);

        $registry->register('enzo_license_status', [
            'default'   => 'inactive',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'license',
            'description' => 'Current license status.',
        ]);

        $registry->register('enzo_license_expiry', [
            'default'   => '',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'license',
            'description' => 'License expiry date (Y-m-d).',
        ]);

        $registry->register('enzo_grace_period_days', [
            'default'   => 7,
            'type'      => 'integer',
            'sanitize'  => 'absint',
            'group'     => 'license',
            'description' => 'Grace period after license failure (D-10 fail-open).',
        ]);

        $registry->register('enzo_local_ai_enabled', [
            'default'   => '0',
            'type'      => 'boolean',
            'sanitize'  => 'rest_sanitize_boolean',
            'group'     => 'ai',
            'description' => 'Enable Local AI Server (Self-Install feature).',
        ]);
        $registry->register('enzo_local_ai_port', [
            'default'   => '31415',
            'type'      => 'integer',
            'sanitize'  => 'absint',
            'group'     => 'ai',
            'description' => 'Port for Local AI Server (default: 31415).',
        ]);
        $registry->register('enzo_local_ai_endpoint', [
            'default'   => '',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'ai',
            'description' => 'Custom endpoint for Local AI Server (overrides auto-discovered).',
        ]);
    }
}