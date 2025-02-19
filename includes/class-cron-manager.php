<?php

/**
 * Import process class
 */

class Cron_Manager
{
    public function __construct()
    {
        add_action('freelance_import_event', array($this, 'import_cron_action'));
    }

    # Manually trigger the CRON job when needed
    public function trigger_import_cron($time = 0)
    {
        wp_schedule_single_event($time, 'freelance_import_event');
        sv_plugin_log('🕒 Import job scheduled.');
    }

    # CRON job action to process one JSON file at a time
    public function import_cron_action()
    {
        # Check if the import job is already running
        if (get_transient('import_json_job_running')) {
            sv_plugin_log("🕒 Import job is already running. Skipping this run.");
            return;
        }

        sv_plugin_log('🕒 Import job started.');

        # Set a transient to indicate that the job is running
        set_transient('import_json_job_running', true, 60 * 60); # 1 hour expiration

        # Get all JSON files
        $json_dir = get_stylesheet_directory() . '/json-files/';
        $archive_dir = $json_dir . 'imported/';

        # Ensure the directories exist
        if (!is_dir($json_dir)) {
            sv_plugin_log("⚠️ JSON directory does not exist: $json_dir");
            delete_transient('import_json_job_running');
            return;
        }

        if (!is_dir($archive_dir)) {
            if (!mkdir($archive_dir, 0755, true)) {
                sv_plugin_log("⚠️ Failed to create archive directory: $archive_dir");
                delete_transient('import_json_job_running');
                return;
            }
        }

        $files = glob($json_dir . '*.json');

        if (empty($files)) {
            sv_plugin_log('✅ All files have been processed. CRON job ended.');
            delete_transient('import_json_job_running');
            return;
        }

        # Process each file
        foreach ($files as $file) {
            $file_path = sanitize_text_field($file);

            # Ensure the file exists
            if (!file_exists($file_path)) {
                sv_plugin_log("⚠️ Invalid or missing file: $file_path");
                continue;
            }

            # Read and process the file
            $file_name = basename($file_path);
            sv_plugin_log("⏳ Processing file: $file_name");

            $file_content = file_get_contents($file_path);
            $data = json_decode($file_content, true);

            # Handle the import
            $importer = new Import_Process();
            $import_success = $importer->import_single_json_file($file_path);

            # Move the file to the archive directory
            if ($import_success) {
                rename($file_path, $archive_dir . basename($file_path));
                sv_plugin_log("📂 File imported and moved to imported directory: $file_path");
            } else {
                sv_plugin_log("❌ Error importing file: $file_path");
            }
        }

        delete_transient('import_json_job_running');
    }
}
