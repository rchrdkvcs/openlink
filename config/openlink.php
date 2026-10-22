<?php

return [

    'analytics' => [
        'via_queue' => env('OPENLINK_ANALYTICS_VIA_QUEUE', false),
    ],

    'diagnostics' => [
        'heartbeat_minutes' => (int) env('OPENLINK_HEARTBEAT_MINUTES', 1),
    ],

    'version' => env('OPENLINK_VERSION', 'dev'),
    'image_tag' => env('OPENLINK_IMAGE_TAG', 'latest'),
    'updater_enabled' => env('OPENLINK_UPDATER_ENABLED', false),
];
