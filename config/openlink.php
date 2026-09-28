<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | Analytics events are written after the response is sent by default, so
    | recording works on every deployment without a queue worker. Instances
    | that run a worker can move the write onto the queue instead.
    |
    */

    'analytics' => [
        'via_queue' => env('OPENLINK_ANALYTICS_VIA_QUEUE', false),
    ],

    'version' => env('OPENLINK_VERSION', 'dev'),
    'image_tag' => env('OPENLINK_IMAGE_TAG', 'latest'),
    'updater_enabled' => env('OPENLINK_UPDATER_ENABLED', false),
];
