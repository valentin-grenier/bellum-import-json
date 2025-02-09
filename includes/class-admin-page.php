<?php

/**
 * Admin page class
 */

class Admin_Page
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_post_sv_import_json', array($this, 'handle_import_json'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_post_sv_delete_imported_files', array($this, 'delete_imported_files'));
    }

    # Admin notices
    public function admin_notices()
    {
        $notices = get_transient('sv_import_json_notices');

        if ($notices) {
            foreach ($notices as $notice) {
                $class = ($notice['type'] === 'error') ? 'notice-error' : 'notice-success';
                echo '<div class="notice ' . esc_attr($class) . ' is-dismissible">';
                echo '<p>' . esc_html($notice['message']) . '</p>';
                echo '</div>';
            }

            delete_transient('sv_import_json_notices'); // Clear notices after displaying them
        }
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

    # Handle the form submission
    public function handle_import_json()
    {
        # Store notices
        $notices = [];

        # Check if nonce is set
        if (!isset($_POST['import_json_nonce_field']) || !wp_verify_nonce($_POST['import_json_nonce_field'], 'import_json_nonce')) {
            set_transient('sv_import_json_notices', [['type' => 'error', 'message' => __('Nonce verification failed.', 'bellum')]], 30);
            wp_redirect(admin_url('admin.php?page=sv-import-json'));
        }

        # Check if the user has the required permissions
        if (!current_user_can('manage_options')) {
            $notices[] = ['type' => 'error', 'message' => __('You do not have permission to perform this action.', 'bellum')];
            wp_redirect(admin_url('admin.php?page=sv-import-json'));
        }

        # Get the uploaded file
        $file = isset($_FILES['json-file']) ? $_FILES['json-file'] : null;

        # Check if the file input is set and not empty
        if (!$file || empty($file['name'])) {
            $notices[] = ['type' => 'error', 'message' => __('No file uploaded.', 'bellum')];
            wp_redirect(admin_url('admin.php?page=sv-import-json'));
        }

        # Check if the file is a JSON file
        if ($file['type'] !== 'application/json') {
            $notices[] = ['type' => 'error', 'message' => __('Invalid file type. Please upload a JSON file.', 'bellum')];
            wp_redirect(admin_url('admin.php?page=sv-import-json'));
        }

        # Start the import process
        $importer = new Import_Process();
        $is_imported = $importer->process_import_json_file($file);

        # Redirect back to the admin page
        wp_redirect(admin_url('admin.php?page=sv-import-json'));

        # If JSON file is successfully imported, display a success message
        if ($is_imported) {
            $notices[] = [
                'type' => 'success',
                'message' => __('JSON file imported successfully.', 'bellum')
            ];
        } else {
            $notices[] = [
                'type' => 'error',
                'message' => __('An error occurred while importing the JSON file.', 'bellum')
            ];
        }
    }

    # Delete all imported files
    function delete_imported_files()
    {
        # Store notices
        $notices = [];

        # Verify nonce for security
        if (
            !isset($_POST['delete_imported_files_nonce_field']) ||
            !wp_verify_nonce($_POST['delete_imported_files_nonce_field'], 'delete_imported_files_nonce')
        ) {
            $notices[] = [
                'type' => 'error',
                'message' => __('Nonce verification failed.', 'bellum')
            ];
        }

        # Define the imported files directory
        $imported_files_dir = get_stylesheet_directory() . '/json-files/imported/';

        # Check if the user has the required permissions
        if (!current_user_can('manage_options')) {
            $notices[] = [
                'type' => 'error',
                'message' => __('You do not have permission to perform this action.', 'bellum')
            ];
        }

        # Flag the starting time of the action
        sv_plugin_log('🗑️ Deleting imported JSON files...');

        # Check if directory exists
        if (!is_dir($imported_files_dir)) {
            sv_plugin_log('⚠️ Imported files directory does not exist.');
            wp_redirect(admin_url('admin.php?page=sv-import-json&'));
            exit;
        }

        # Get all files in the directory
        $files = glob($imported_files_dir . '*.json');

        # Delete each file
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                sv_plugin_log("🗑️ Deleted file: " . basename($file));
            }
        }

        sv_plugin_log('✅ All imported JSON files have been deleted.');

        # Redirect back to the admin page with a success message
        wp_redirect(admin_url('admin.php?page=sv-import-json'));
        exit;
    }
}
