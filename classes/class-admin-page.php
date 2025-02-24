<?php

class Admin_Page
{
    private $upload_dir;
    private $queue_dir;
    private $imported_dir;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_post_sv_import_json', array($this, 'handle_form_submission'));
        add_action('admin_post_sv_delete_queue_files', array($this, 'delete_queue_files'));
        add_action('admin_post_sv_delete_imported_files', array($this, 'delete_imported_files'));

        $this->upload_dir = WP_CONTENT_DIR . '/json-files/';
        $this->queue_dir = $this->upload_dir . 'queue/';
        $this->imported_dir = $this->upload_dir . 'imported/';
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

    # Render admin page
    public function render_page()
    {
        require_once SV_IMPORT_JSON_DIR . 'views/admin-page.php';
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

            # Clear notices
            delete_transient('sv_import_json_notices');
        }
    }

    # Handle the form submission
    public function handle_form_submission()
    {
        # Check if nonce is set
        if (!isset($_POST['sv_upload_json_nonce']) || !wp_verify_nonce($_POST['sv_upload_json_nonce'], 'sv_upload_json_action')) {
            set_transient('sv_import_json_notices', [['type' => 'error', 'message' => __('Nonce verification failed.', 'bellum')]], 30);
            wp_redirect(admin_url('admin.php?page=sv-import-json'));
            exit;
        }

        # Check if the user has the required permissions
        if (!current_user_can('manage_options')) {
            set_transient('sv_import_json_notices', [['type' => 'error', 'message' => __('You do not have permission to upload files.', 'bellum')]], 30);
            wp_redirect(admin_url('admin.php?page=sv-import-json'));
            exit;
        }

        # Get the uploaded file
        $file = isset($_FILES['json_file']) ? $_FILES['json_file'] : null;

        # Check if the file input is set and not empty
        if (!$file || empty($file['name'])) {
            set_transient('sv_import_json_notices', [['type' => 'error', 'message' => __('Please select a file to upload.', 'bellum')]], 30);
            wp_redirect(admin_url('admin.php?page=sv-import-json'));
            exit;
        }

        # Check if the file is a JSON file
        if ($file['type'] !== 'application/json') {
            set_transient('sv_import_json_notices', [['type' => 'error', 'message' => __('Please upload a valid JSON file.', 'bellum')]], 30);
            wp_redirect(admin_url('admin.php?page=sv-import-json'));
            exit;
        }

        # Display a success message
        set_transient('sv_import_json_notices', [['type' => 'success', 'message' => __('File uploaded successfully! The import process whill start soon.', 'bellum')]], 30);

        # Process JSON file
        if (!$this->process_json_file($file)) {
            # Display an error message
            set_transient('sv_import_json_notices', [['type' => 'error', 'message' => __('An error occurred while processing the JSON file.', 'bellum')]], 30);

            # Redirect back to the admin page
            wp_safe_redirect(admin_url('admin.php?page=sv-import-json&status=error'));
            exit;
        }

        # Redirect back to the admin page
        wp_safe_redirect(admin_url('admin.php?page=sv-import-json&status=success'));
        exit;
    }

    public function process_json_file($file)
    {
        # Move the file to the "queue" directory
        $file_path = $this->queue_dir . $file['name'];

        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            set_transient('sv_import_json_notices', [['type' => 'error', 'message' => __('An error occurred while uploading the file.', 'bellum')]], 30);

            sv_plugin_log('Error moving file to queue directory.');
            wp_redirect(admin_url('admin.php?page=sv-import-json'));
            exit;
        }

        move_uploaded_file($file['tmp_name'], $file_path);

        # If the file exceed 5MB, split it into chunks
        if ($file['size'] > 5000000) {
            $this->split_json_file($file_path);
        }

        return true;
    }

    public function split_json_file($file_path)
    {
        # Read the JSON file
        $file_contents = file_get_contents($file_path);
        $data = json_decode($file_contents, true);

        if (empty($data)) {
            sv_plugin_log("❌ Error: Unable to read the JSON file.");
            return false;
        }

        # Split the data into chunks
        $chunks = array_chunk($data, 200);
        $file_basename = pathinfo($file_path, PATHINFO_FILENAME);

        foreach ($chunks as $index => $chunk) {
            # Write the chunk to a new file
            $chunk_file_name = "{$file_basename}_part-{$index}.json";
            $chunk_file = $this->queue_dir . $chunk_file_name;

            # Fill the chunk file with the data
            file_put_contents($chunk_file, json_encode($chunk, JSON_PRETTY_PRINT));

            sv_plugin_log("📂 Creating file: $chunk_file_name");
        }

        sv_plugin_log("✅ Large JSON file successfully split.");

        # Delete the original file
        try {
            if (unlink($file_path)) {
                sv_plugin_log("🗑️ Original JSON file deleted.");
            } else {
                sv_plugin_log("❌ Failed to delete large JSON file.");
                return false;
            }
        } catch (Exception $e) {
            sv_plugin_log("❌ Exception occurred while deleting large JSON file: " . $e->getMessage());
            return false;
        }

        return true;
    }

    public function delete_imported_files()
    {
        # Ensure the directory exists
        if (!is_dir($this->imported_dir)) {
            set_transient('sv_import_json_notices', [['type' => 'error', 'message' => __('The imported directory does not exist.', 'bellum')]], 30);
            wp_redirect(admin_url('admin.php?page=sv-import-json'));
            exit;
        }

        # Get files from directory
        $imported_files = array_diff(scandir($this->imported_dir), ['..', '.']);

        # Delete imported files
        foreach ($imported_files as $file) {
            $file_path = $this->imported_dir . $file;

            if (is_file($file_path)) {
                unlink($file_path);
            }
        }

        # Display a success message
        set_transient('sv_import_json_notices', [['type' => 'success', 'message' => __('Imported files deleted successfully.', 'bellum')]], 30);
        sv_plugin_log("🗑️ Imported files deleted by user.");

        # Redirect back to the admin page
        wp_safe_redirect(admin_url('admin.php?page=sv-import-json'));
        exit;
    }

    public function delete_queue_files()
    {
        # Ensure the directory exists
        if (!is_dir($this->queue_dir)) {
            set_transient('sv_import_json_notices', [['type' => 'error', 'message' => __('The queue directory does not exist.', 'bellum')]], 30);
            wp_redirect(admin_url('admin.php?page=sv-import-json'));
            exit;
        }

        # Get files from directory
        $queue_files = array_diff(scandir($this->queue_dir), ['..', '.']);

        # Delete queue files
        foreach ($queue_files as $file) {
            $file_path = $this->queue_dir . $file;

            if (is_file($file_path)) {
                unlink($file_path);
            }
        }

        # Remove lock file
        $lock_file = new JSON_Importer;
        $lock_file->remove_lock();

        # Display a success message
        set_transient('sv_import_json_notices', [['type' => 'success', 'message' => __('Queue files deleted successfully.', 'bellum')]], 30);
        sv_plugin_log("🗑️ Queue files deleted by user.");

        # Redirect back to the admin page
        wp_safe_redirect(admin_url('admin.php?page=sv-import-json'));
        exit;
    }
}
