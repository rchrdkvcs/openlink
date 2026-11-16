<?php

use App\Services\ErrorReporting\TracesSampler;

return [

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    'release' => env('SENTRY_RELEASE', env('OPENLINK_VERSION')),

    'environment' => env('SENTRY_ENVIRONMENT'),

    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),

    'traces_sampler' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0) > 0 ? [TracesSampler::class, 'sample'] : null,

    'profiles_sample_rate' => null,

    'send_default_pii' => false,

    'data_collection' => [
        'user_info' => false,
        'cookies' => ['mode' => 'off'],
        'http_headers' => [
            'request' => [
                'mode' => 'allowList',
                'terms' => ['accept', 'content-type', 'x-inertia', 'x-inertia-version', 'x-requested-with'],
            ],
            'response' => ['mode' => 'off'],
        ],
        'http_bodies' => [],
        'url_query_params' => ['mode' => 'off'],
        'gen_ai' => ['inputs' => false, 'outputs' => false],
        'database_query_data' => false,
        'queues' => false,
        'stack_frame_variables' => false,
    ],

    'enable_logs' => false,

    'enable_metrics' => false,

    'ignore_transactions' => ['/up'],

];
