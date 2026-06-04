<?php
/**
 * Plugin Name: SEO Copilot
 * Description: AI-powered SEO content for any post type. Granular per-field, per-template control. Fluent 2 UI.
 * Version: 1.1.3
 * Author: Ale Aruca
 * License: GPL-2.0-or-later
 * Text Domain: seo-copilot
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('SEOCP_VERSION', '1.1.3');
define('SEOCP_FILE', __FILE__);
define('SEOCP_DIR', plugin_dir_path(__FILE__));
define('SEOCP_URL', plugin_dir_url(__FILE__));
define('SEOCP_BASENAME', plugin_basename(__FILE__));
define('SEOCP_DB_PREFIX', 'seocp_');
define('SEOCP_OPTION_KEY', 'seocp_settings');
define('SEOCP_REST_NS', 'seocp/v1');

// PSR-4 autoloader for SeoCopilot\ namespace.
spl_autoload_register(static function ($class) {
    $prefix = 'SeoCopilot\\';
    $base   = SEOCP_DIR . 'src/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, [\SeoCopilot\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\SeoCopilot\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function () {
    \SeoCopilot\Plugin::instance()->boot();
});

if (!function_exists('seocp_partial')) {
    function seocp_partial($name, array $vars = []) {
        $path = SEOCP_DIR . 'views/' . ltrim($name, '/') . '.php';
        if (!file_exists($path)) {
            return;
        }
        extract($vars, EXTR_SKIP);
        include $path;
    }
}
