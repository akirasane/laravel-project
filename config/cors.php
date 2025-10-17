<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => explode(',', env('SECURITY_CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS')),

    'allowed_origins' => array_filter(explode(',', env('SECURITY_CORS_ALLOWED_ORIGINS', ''))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => explode(',', env('SECURITY_CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With,X-CSRF-TOKEN')),

    'exposed_headers' => array_filter(explode(',', env('SECURITY_CORS_EXPOSED_HEADERS', ''))),

    'max_age' => env('SECURITY_CORS_MAX_AGE', 86400),

    'supports_credentials' => env('SECURITY_CORS_SUPPORTS_CREDENTIALS', false),

];