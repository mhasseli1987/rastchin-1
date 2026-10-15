<?php
/**
 * Plugin Name:       ENZO - پلتفرم هوشمند مدیریت و تولید محتوای تلگرام و بله
 * Plugin URI:        https://rtl-theme.com/plugins/enzo
 * Description:       پلتفرم کامل هوشمند برای مدیریت و تولید محتوای تلگرام و بله — تولید محتوا، بازنویسی، ترجمه، خلاصه‌سازی، بهینه‌سازی سئو، استخراج هشتگ هوشمند، ارسال آلبوم عکس (Media Group)، زمان‌بندی انتشار و صف انتشار اتمیک. پشتیبانی کامل از ۴ پلتفرم تلگرام، بله، ایتا و روبیکا. نصب خودکار سرور هوش مصنوعی محلی و پشتیبانی از providerهای رایگان.
 * Version:           1.0.0
 * Author:            RTL-Theme
 * Author URI:        https://rtl-theme.com
 * License:           GPL-2.0+
 * Text Domain:       enzo-telegram-ai-admin
 * Domain Path:       /languages
 * Requires PHP:      8.1
 * Requires WP:       6.5
 *
 * @package ENZO
 */

defined('ABSPATH') || exit;

define('ENZO_VERSION', '1.0.0');
define('ENZO_FILE', __FILE__);
define('ENZO_DIR', plugin_dir_path(__FILE__));
define('ENZO_URL', plugin_dir_url(__FILE__));
define('ENZO_INCLUDES', ENZO_DIR . 'includes');
define('ENZO_NS', 'ENZO');

// Version stamp for DB migrations.
define('ENZO_DB_VERSION', '1.0.0');

/**
 * PSR-4 style autoloader: ENZO\Foo\Bar -> includes/Foo/Bar.php
 * The plugin previously had no autoloader at all (fatal on boot).
 */
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'ENZO\\', 5) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, 5));
    $file = ENZO_INCLUDES . '/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Hooks live here only (Plugin.php used to register the same hooks twice
// with a nonexistent ENZO\Core_Plugin class in this file).
register_activation_hook(__FILE__, ['ENZO\\Core\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['ENZO\\Core\\Plugin', 'deactivate']);
register_uninstall_hook(__FILE__, ['ENZO\\Core\\Plugin', 'uninstall']);

ENZO\Core\Plugin::instance()->boot();
