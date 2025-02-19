<?php

class JSON_File_Uploader
{
    private $upload_dir;
    private $queue_dir;

    public function __construct()
    {
        $this->upload_dir = WP_CONTENT_DIR . '/json-files/';
        $this->queue_dir = $this->upload_dir . 'queue/';
        add_action('admin_post_sv_upload_json', [$this, 'handle_upload']);
    }


    public function handle_upload() {}
}
