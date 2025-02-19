<?php
class JSON_Importer
{
    private $lock_file;
    private $json_dir;
    private $queue_dir;
    private $proceeding_dir;
    private $imported_dir;

    public function __construct()
    {
        $this->lock_file = WP_CONTENT_DIR . '/json-files/import.lock';
        $this->json_dir = WP_CONTENT_DIR . '/json-files';
        $this->queue_dir = $this->json_dir . '/queue';
        $this->proceeding_dir = $this->json_dir . '/proceeding';
        $this->imported_dir = $this->json_dir . '/imported';

        add_action('init', [$this, 'schedule_cron']);
        add_action('json_import_cron', [$this, 'process_json_files']);
        add_action('init', [$this, 'check_directories']);
    }

    public function check_directories()
    {
        if (!file_exists($this->json_dir)) {
            mkdir($this->json_dir, 0755, true);
        }

        if (!file_exists($this->queue_dir)) {
            mkdir($this->queue_dir, 0755, true);
        }

        if (!file_exists($this->proceeding_dir)) {
            mkdir($this->proceeding_dir, 0755, true);
        }

        if (!file_exists($this->imported_dir)) {
            mkdir($this->imported_dir, 0755, true);
        }
    }

    public function schedule_cron()
    {
        if (!wp_next_scheduled('json_import_cron')) {
            wp_schedule_event(time(), 'every_five_minutes', 'json_import_cron');
        }
    }

    public function process_json_files()
    {
        # Check if another process is running
        if ($this->is_locked()) {
            sv_plugin_log('JSON Import: Process already running, exiting.');
            return;
        }

        # Else, create lock file
        $this->create_lock();

        # Look for JSON files in the queue directory
        $files = glob($this->json_dir . '/*.json');

        if (empty($files)) {
            //sv_plugin_log('JSON Import: No new files found.');
            $this->remove_lock();
            return;
        }

        # Move each file to /proceeding and process it
        foreach ($files as $file) {
            $filename = basename($file);
            $new_path = $this->proceeding_dir . '/' . $filename;

            # Move to /proceeding
            rename($file, $new_path);
            sv_plugin_log("JSON Import: Moved $filename to /proceeding/");

            # Process the file and check for errors
            $this->process_file($new_path);

            # Move to /imported
            rename($new_path, $this->imported_dir . '/' . $filename);
            sv_plugin_log("JSON Import: Moved $filename to /imported/");
        }

        # Remove lock file
        $this->remove_lock();
    }

    private function process_file($file_path)
    {
        # Define file name from path
        $file_name = basename($file_path);

        sv_plugin_log("Processing: $file_name");


        $json_data = file_get_contents($file_path);
        $entries = json_decode($json_data, true);

        # Process each entry in the JSON file
        foreach ($entries as $entry) {
            sv_plugin_log("Fake processing: " . $entry['title']);

            # Wait 5 seconds after import success
            sleep(5);
        }
    }

    private function is_locked()
    {
        if (!file_exists($this->lock_file)) {
            return false;
        }

        # Check if lock file is older than 30 minutes
        $lock_time = filemtime($this->lock_file);
        $timeout = 30 * 60;

        # Remove lock file if it's too old
        if (time() - $lock_time > $timeout) {
            sv_plugin_log('JSON Import: Lock file expired, removing it.');
            unlink($this->lock_file);
            return false;
        }

        return true;
    }

    private function create_lock()
    {
        file_put_contents($this->lock_file, time());
    }

    private function remove_lock()
    {
        if (file_exists($this->lock_file)) {
            unlink($this->lock_file);
        }
    }
}
