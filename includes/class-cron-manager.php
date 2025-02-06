<?php

/**
 * Cron manager class
 */

class Cron_Manager
{
    public function __construct()
    {
        add_action('init', array($this, 'schedule_cron'));
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        add_action('freelance_import_event', array($this, 'import_cron_action'));
    }

    # Schedule the cron event
    public function schedule_cron()
    {
        if (!wp_next_scheduled('freelance_import_event')) {
            wp_schedule_event(time(), 'every_half_hour', 'freelance_import_event');

            sv_plugin_log("Scheduled the 'freelance_import_event'.");
        }
    }

    # Add a custom CRON interval
    public function add_cron_interval($schedules)
    {
        $schedules['every_half_hour'] = array(
            'interval' => 30 * 60,
            'display' => __('Every 30mn', 'bellum')
        );

        return $schedules;
    }

    # CRON job action to process one JSON file at a time
    public function import_cron_action()
    {
        $json_dir = get_stylesheet_directory() . '/json-files/';
        $archive_dir = $json_dir . 'imported/';

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

        # Debug log to indicate the file being processed
        sv_plugin_log("📂 Fichier en cours de traitement: $file");

        # Process the file
        $importer = new Import_Process();
        $import_success = $importer->import_single_json_file($file_path);

        # If import is successful, move the file to the 'imported' folder
        if ($import_success) {
            rename($file_path, $archive_dir . $file);
            sv_plugin_log("📂 Fichier importé et archivé: $file");
        } else {
            sv_plugin_log("❌ Erreur lors de l'import du fichier: $file");
        }

        # Check if there are still files left
        $remaining_files = array_diff(scandir($json_dir), array('..', '.'));

        if (!empty($remaining_files)) {
            error_log('--------------------------------------------------------');
            error_log('↪️ Il reste des fichiers dans le dossier. CRON relancé.');

            # Schedule the CRON event again immediately
            wp_schedule_single_event(time() + 10, 'freelance_import_event');
        } else {
            sv_plugin_log('✅ Tous les fichiers ont été traités. CRON stoppé.');
        }
    }
}
