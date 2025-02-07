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
        $notices = array();

        # Check if nonce is set
        if (!isset($_POST['import_json_nonce_field']) || !wp_verify_nonce($_POST['import_json_nonce_field'], 'import_json_nonce')) {
            $notices[] = ['type' => 'error', 'message' => __('Nonce verification failed.', 'bellum')];
        }

        # Check if the user has the required permissions
        if (!current_user_can('manage_options')) {
            $notices[] = ['type' => 'error', 'message' => __('You do not have permission to perform this action.', 'bellum')];
        }

        # Check if the file input is set and not empty
        if (!isset($_FILES['json-file']) || empty($_FILES['json-file']['name'])) {
            $notices[] = ['type' => 'error', 'message' => __('No file uploaded.', 'bellum')];
        } else {
            $file = $_FILES['json-file'];

            # Check if the file is a JSON file
            if ($file['type'] !== 'application/json') {
                $notices[] = ['type' => 'error', 'message' => __('Invalid file type. Please upload a JSON file.', 'bellum')];
            } else {
                # Upload file to the dedicated folder, in themes/bellum/json-files/
                $upload_dir = get_stylesheet_directory() . '/json-files/';

                if (!file_exists($upload_dir)) {
                    wp_mkdir_p($upload_dir);
                }

                if (!is_writable($upload_dir)) {
                    $notices[] = ['type' => 'error', 'message' => __('Upload directory is not writable.', 'bellum')];
                } else {
                    $upload_path = $upload_dir . basename($file['name']);

                    # Check if the file was uploaded successfully
                    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $notices[] = ['type' => 'error', 'message' => __('File upload failed.', 'bellum')];
                    } else {
                        $notices[] = ['type' => 'success', 'message' => __('File uploaded successfully.', 'bellum')];

                        sv_plugin_log('📂 JSON file uploaded: ' . $file['name']);
                        die();
                    }

                    # Store the file path for CRON processing
                    update_option('sv_import_json_file', $upload_path);

                    # Schedule the CRON job to process the JSON file
                    if (!wp_next_scheduled('sv_process_import_json_cron')) {
                        wp_schedule_event(time(), 'hourly', 'freelance_import_event');
                        sv_plugin_log("🕒 Scheduled the 'sv_process_import_json_cron'.");
                    }
                }
            }
        }

        # Store notices in a transient
        if (!empty($notices)) {
            set_transient('sv_import_json_notices', $notices, 30);
        }

        # Redirect back to the admin page
        wp_redirect(admin_url('admin.php?page=sv-import-json'));
        exit;
    }
}
