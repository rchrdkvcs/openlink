<?php

return [

    'analytics' => [
        'via_queue' => env('OPENLINK_ANALYTICS_VIA_QUEUE', false),
    ],

    'diagnostics' => [
        'heartbeat_minutes' => (int) env('OPENLINK_HEARTBEAT_MINUTES', 1),
    ],

    'webhooks' => [
        'max_attempts' => (int) env('OPENLINK_WEBHOOK_MAX_ATTEMPTS', 6),
        'timeout' => (int) env('OPENLINK_WEBHOOK_TIMEOUT', 10),
        'connect_timeout' => (int) env('OPENLINK_WEBHOOK_CONNECT_TIMEOUT', 3),
        'retention_days' => (int) env('OPENLINK_WEBHOOK_RETENTION_DAYS', 30),
    ],

    'version' => env('OPENLINK_VERSION', 'dev'),
    'image_tag' => env('OPENLINK_IMAGE_TAG', 'latest'),
    'updater_enabled' => env('OPENLINK_UPDATER_ENABLED', false),
];
