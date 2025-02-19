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
require_once SV_IMPORT_JSON_DIR . 'classes/class-admin-page.php';

# Autoload dependencies if the vendor directory exists
if (file_exists(SV_IMPORT_JSON_DIR . 'vendor/autoload.php')) {
    require_once SV_IMPORT_JSON_DIR . 'vendor/autoload.php';
}

# Debug utility class
if (!function_exists('sv_plugin_log')) {
    function sv_plugin_log($message)
    {
        $log_file = SV_IMPORT_JSON_DIR . '/bellum-plugin-debug.log';

        // Check if log file is writable
        if (is_writable($log_file)) {
            // Log the message to the file
            error_log('[' . date('Y-m-d H:i:s') . '] ' . print_r($message, true) . "\n", 3, $log_file);
        } else {
            // Log an error if the file is not writable
            error_log('Log file is not writable');
        }
    }
}

# Initialize the plugin
function sv_import_json_init()
{
    new Admin_Page();
}
add_action('plugins_loaded', 'sv_import_json_init');
