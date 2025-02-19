<?php

/**
 * Plugin Name: SV - Import JSON
 * Description: Custom plugin made for Bellum to import JSON files and create freelance profiles.
 * Version: 1.0
 * Author: Valentin Grenier • Studio Val
 * Author URI: https://studio-val.fr
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bellum
 */

# Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

define('SV_IMPORT_JSON_DIR', plugin_dir_path(__FILE__));
define('SV_IMPORT_JSON_URL', plugin_dir_url(__FILE__));

# Include required plugin classes
require_once SV_IMPORT_JSON_DIR . 'includes/class-import-process.php';
require_once SV_IMPORT_JSON_DIR . 'includes/class-admin-page.php';
require_once SV_IMPORT_JSON_DIR . 'includes/class-cron-manager.php';
require_once SV_IMPORT_JSON_DIR . 'includes/class-plugin-assets.php';

# Autoload dependencies if the vendor directory exists
if (file_exists(SV_IMPORT_JSON_DIR . 'vendor/autoload.php')) {
    require_once SV_IMPORT_JSON_DIR . 'vendor/autoload.php';
}

# Register the activation hook
register_activation_hook(
    __FILE__,
    function () {
        wp_schedule_event(time(), 'daily', 'freelance_import_event');
    }
);

# Trigger the deactivation hook
register_deactivation_hook(
    __FILE__,
    function () {
        wp_clear_scheduled_hook('freelance_import_event');
    }
);

# Debug utility class
if (!function_exists('sv_plugin_log')) {
    function sv_plugin_log($message)
    {
        $log_file = SV_IMPORT_JSON_DIR . '/bellum-plugin-debug.log';
        error_log('[' . date('Y-m-d H:i:s') . '] ' . print_r($message, true) . "\n", 3, $log_file);
    }
}

# Initialize the plugin
function sv_import_json_init()
{
    new Import_Process();
    new Admin_Page();
    new Cron_Manager();
    new Plugin_Assets();
}
add_action('plugins_loaded', 'sv_import_json_init');
