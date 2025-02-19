<?php

/**
 * Admin page class
 */

class Admin_Page
{
    private $upload_dir;
    private $queue_dir;

    public function __construct()
    {
        $this->upload_dir = WP_CONTENT_DIR . '/json-files/';
        $this->queue_dir = $this->upload_dir . 'queue/';

        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_post_sv_upload_json', array($this, 'handle_upload'));
    }

    # Add the menu page
    public function add_menu()
    {
        add_menu_page(
            'Importer JSON',
            'Importer JSON',
            'manage_options',
            'sv-import-json',
            array($this, 'render_page'),
            'dashicons-upload',
            6
        );
    }

    # Render the page
    public function render_page()
    {
        require_once SV_IMPORT_JSON_DIR . 'views/admin-page.php';
    }

    # Handle the file upload
    public function handle_upload()
    {
        sv_plugin_log('File uploaded');
    }
}
