<?php

/**
 * Plugin stylesheets and scripts
 */

class Plugin_Assets
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function enqueue_admin_assets()
    {
        # CSS files
        wp_enqueue_style('plugin-admin-styles', SV_IMPORT_JSON_URL . 'assets/css/admin-styles.css', array(), '1.0.0', 'all');
    }
}
