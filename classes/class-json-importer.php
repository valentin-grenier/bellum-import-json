<?php
class JSON_Importer
{
    private $lock_file;
    private $upload_dir;
    private $queue_dir;
    private $proceeding_dir;
    private $imported_dir;

    public function __construct()
    {
        $this->lock_file = WP_CONTENT_DIR . '/json-files/import.lock';
        $this->upload_dir = WP_CONTENT_DIR . '/json-files';
        $this->queue_dir = $this->upload_dir . '/queue';
        $this->proceeding_dir = $this->upload_dir . '/proceeding';
        $this->imported_dir = $this->upload_dir . '/imported';

        add_action('init', [$this, 'schedule_cron']);
        add_action('json_import_cron', [$this, 'process_json_files']);
    }

    public function check_directories()
    {
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
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
        if ($this->is_locked()) {
            sv_plugin_log('JSON Import: Process already running, exiting.');
            return;
        }

        $this->create_lock();

        $files = glob($this->queue_dir . '/*.json');

        if (empty($files)) {
            sv_plugin_log('JSON Import: No files to process.');
            $this->remove_lock();
            return;
        }

        foreach ($files as $file) {
            $filename = basename($file);
            $new_path = $this->proceeding_dir . '/' . $filename;

            # Move to /proceeding
            rename($file, $new_path);
            sv_plugin_log("JSON Import: Moved $filename to /proceeding/");

            $this->process_file($new_path);

            rename($new_path, $this->imported_dir . '/' . $filename); # Move to /imported
            sv_plugin_log("JSON Import: Moved $filename to /imported/");
        }

        $this->remove_lock();
    }

    private function process_file($file_path)
    {
        # Your JSON processing logic here
        sv_plugin_log("Processing: $file_path");

        $json_data = file_get_contents($file_path);
        $entries = json_decode($json_data, true);

        foreach ($entries as $entry) {
            # Import logic (to be implemented)
            sv_plugin_log("[Fake import]");
            sleep(5);
        }
    }

    private function is_locked()
    {
        if (!file_exists($this->lock_file)) {
            return false;
        }

        $lock_time = filemtime($this->lock_file);
        $timeout = 30 * 60; # 30 minutes

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
