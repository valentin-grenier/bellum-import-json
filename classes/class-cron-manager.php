<?php

class Cron_Manager
{
    public function __construct()
    {
        add_filter('cron_schedules', [$this, 'custom_cron_schedules']);
    }

    public function custom_cron_schedules($schedules)
    {
        $schedules['every_five_minutes'] = [
            'interval' => 300,
            'display'  => __('Every 5 Minutes')
        ];
        return $schedules;
    }
}
