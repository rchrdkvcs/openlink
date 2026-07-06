<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The JSON API under /api is consumed by browser extensions and other
    | external clients, so it accepts requests from any origin. This is safe
    | because API authentication uses bearer tokens (Laravel Sanctum personal
    | access tokens), never cookies: supports_credentials must stay false.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
