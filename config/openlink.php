<?php

return [

    'analytics' => [
        'via_queue' => env('OPENLINK_ANALYTICS_VIA_QUEUE', false),
    ],

    'domain_edge' => [
        'driver' => env('OPENLINK_DOMAIN_EDGE', 'direct'),
        'secrets' => array_values(array_filter(array_map('trim', explode(',', (string) env('OPENLINK_EDGE_SECRET', ''))))),
        'cloudflare' => [
            'api_token' => env('OPENLINK_CLOUDFLARE_API_TOKEN'),
            'zone_id' => env('OPENLINK_CLOUDFLARE_ZONE_ID'),
        ],
    ],

    'diagnostics' => [
        'heartbeat_minutes' => (int) env('OPENLINK_HEARTBEAT_MINUTES', 1),
    ],

    'webhooks' => [
        'max_attempts' => max(1, (int) env('OPENLINK_WEBHOOK_MAX_ATTEMPTS', 6)),
        'timeout' => max(1, (int) env('OPENLINK_WEBHOOK_TIMEOUT', 10)),
        'connect_timeout' => max(1, (int) env('OPENLINK_WEBHOOK_CONNECT_TIMEOUT', 3)),
        'retention_days' => max(1, (int) env('OPENLINK_WEBHOOK_RETENTION_DAYS', 30)),
    ],

    'version' => env('OPENLINK_VERSION', 'dev'),
    'image_tag' => env('OPENLINK_IMAGE_TAG', 'latest'),
    'updater_enabled' => env('OPENLINK_UPDATER_ENABLED', false),
];
