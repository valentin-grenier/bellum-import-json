<?php

class Init
{
    private $upload_dir;
    private $queue_dir;
    private $processing_dir;
    private $imported_dir;

    public function __construct()
    {
        add_action('init', array($this, 'check_required_directories'));


        $this->upload_dir = WP_CONTENT_DIR . '/json-files/';
        $this->queue_dir = $this->upload_dir . 'queue/';
        $this->processing_dir = $this->upload_dir . 'processing/';
        $this->imported_dir = $this->upload_dir . 'imported/';
    }

    # Check for required directories
    public function check_required_directories()
    {
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }

        if (!file_exists($this->queue_dir)) {
            mkdir($this->queue_dir, 0755, true);
        }

        if (!file_exists($this->processing_dir)) {
            mkdir($this->processing_dir, 0755, true);
        }

        if (!file_exists($this->imported_dir)) {
            mkdir($this->imported_dir, 0755, true);
        }
    }
}
