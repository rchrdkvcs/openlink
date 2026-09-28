<?php

return [

    'analytics' => [
        'via_queue' => env('OPENLINK_ANALYTICS_VIA_QUEUE', false),
    ],

    'version' => env('OPENLINK_VERSION', 'dev'),
    'image_tag' => env('OPENLINK_IMAGE_TAG', 'latest'),
    'updater_enabled' => env('OPENLINK_UPDATER_ENABLED', false),
];
