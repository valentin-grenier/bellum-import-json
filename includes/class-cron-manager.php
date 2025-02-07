<?php

/**
 * Cron manager class
 */

class Cron_Manager
{
    public function __construct()
    {
        add_action('init', array($this, 'schedule_cron'));
        add_action('freelance_import_event', array($this, 'import_cron_action'));
    }

    # Schedule the cron event
    public function schedule_cron()
    {
        # Schedule the cron event if it's not already scheduled
        if (!wp_next_scheduled('freelance_import_event')) {
            wp_schedule_event(time(), 'hourly', 'freelance_import_event');
            sv_plugin_log("🕒 Scheduled the 'freelance_import_event'.");
        }
    }

    # CRON job action to process one JSON file at a time
    public function import_cron_action()
    {
        $json_dir = get_stylesheet_directory() . '/json-files/';
        $archive_dir = $json_dir . 'imported/';
        $batch_size = 500; # Number of records to process at a time

        # Create the 'imported' folder if it doesn't exist
        if (!file_exists($archive_dir)) {
            mkdir($archive_dir, 0755, true);
        }

        # Get all files in the json-files directory (excluding '.' and '..')
        $files = array_diff(scandir($json_dir), array('..', '.'));

        if (empty($files)) {
            # No files to process
            error_log('✅ Tous les fichiers ont été traités. CRON stoppé.');
            return;
        }

        # Process only the first file
        $file = reset($files);
        $file_path = $json_dir . sanitize_text_field($file);

        # Ensure it's a file (avoid directory issues)
        if (!is_file($file_path)) {
            sv_plugin_log("⚠️ Fichier ignoré (invalide): $file");
            return;
        }

        # Read the JSON file
        $file_contents = file_get_contents($file_path);
        $data = json_decode($file_contents, true);

        # Determine last processed index
        $last_processed_index = get_option('last_processed_index_' . $file, 0) ?: 0;

        # Process the records in batches
        $chunk = array_slice($data, $last_processed_index, $batch_size);

        if (!empty($chunk)) {
            # Debug log to indicate the file being processed
            sv_plugin_log("📂 Fichier en cours de traitement: $file");

            # Process the current batch
            $importer = new Import_Process();
            $import_success = $importer->import_single_json_file($archive_dir . $file_path);

            # If import is successful, move the file to the 'imported' folder
            if ($import_success) {
                $last_processed_index += count($chunk);
                update_option('last_processed_index_' . $file, $last_processed_index);

                # If all records have been processed, move the file to the 'imported' folder
                if ($last_processed_index >= count($data)) {
                    rename($file_path, $archive_dir . $file);
                    delete_option('last_processed_index_' . $file);
                    sv_plugin_log("📂 Fichier importé et archivé: $file");
                }
            } else {
                sv_plugin_log("❌ Erreur lors de l'import du fichier: $file");
            }
        }

        # If there are still records to process, schedule the CRON job again
        if ($last_processed_index < count($data)) {
            wp_schedule_single_event(time() + 10, 'freelance_import_event'); // Retry after 10 seconds
            sv_plugin_log("↪️ Fichier non terminé, relance de l'import.");
        } else {
            sv_plugin_log('✅ Tous les fichiers ont été traités. CRON stoppé.');
        }
    }
}
