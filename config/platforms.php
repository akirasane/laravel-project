<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for e-commerce platform integrations including security
    | settings, rate limits, and environment-specific endpoints.
    |
    */

    'request_timeout' => env('PLATFORM_REQUEST_TIMEOUT', 30),
    'max_request_size' => env('PLATFORM_MAX_REQUEST_SIZE', 1048576), // 1MB
    'max_redirects' => env('PLATFORM_MAX_REDIRECTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'shopee' => [
            'requests_per_minute' => env('SHOPEE_RATE_LIMIT', 100),
            'burst_limit' => env('SHOPEE_BURST_LIMIT', 10),
        ],
        'lazada' => [
            'requests_per_minute' => env('LAZADA_RATE_LIMIT', 60),
            'burst_limit' => env('LAZADA_BURST_LIMIT', 5),
        ],
        'shopify' => [
            'requests_per_minute' => env('SHOPIFY_RATE_LIMIT', 40),
            'burst_limit' => env('SHOPIFY_BURST_LIMIT', 4),
        ],
        'tiktok' => [
            'requests_per_minute' => env('TIKTOK_RATE_LIMIT', 120),
            'burst_limit' => env('TIKTOK_BURST_LIMIT', 12),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform API Endpoints
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'shopee' => [
            'sandbox' => env('SHOPEE_SANDBOX_URL', 'https://partner.test-stable.shopeemobile.com'),
            'production' => env('SHOPEE_PRODUCTION_URL', 'https://partner.shopeemobile.com'),
            'allowed_domains' => [
                'partner.shopeemobile.com',
                'partner.test-stable.shopeemobile.com',
            ],
        ],
        'lazada' => [
            'sandbox' => env('LAZADA_SANDBOX_URL', 'https://api.lazada.com/rest'),
            'production' => env('LAZADA_PRODUCTION_URL', 'https://api.lazada.com/rest'),
            'allowed_domains' => [
                'api.lazada.com',
                'api.lazada.co.th',
                'api.lazada.com.my',
                'api.lazada.sg',
                'api.lazada.com.ph',
                'api.lazada.vn',
            ],
        ],
        'shopify' => [
            'sandbox' => env('SHOPIFY_SANDBOX_URL', 'https://{shop}.myshopify.com'),
            'production' => env('SHOPIFY_PRODUCTION_URL', 'https://{shop}.myshopify.com'),
            'allowed_domains' => [
                'myshopify.com',
                'shopify.com',
            ],
        ],
        'tiktok' => [
            'sandbox' => env('TIKTOK_SANDBOX_URL', 'https://open-api-sandbox.tiktokglobalshop.com'),
            'production' => env('TIKTOK_PRODUCTION_URL', 'https://open-api.tiktokglobalshop.com'),
            'allowed_domains' => [
                'open-api.tiktokglobalshop.com',
                'open-api-sandbox.tiktokglobalshop.com',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'verify_ssl' => env('PLATFORM_VERIFY_SSL', true),
        'allow_self_signed' => env('PLATFORM_ALLOW_SELF_SIGNED', false),
        'webhook_signature_validation' => env('PLATFORM_WEBHOOK_SIGNATURE_VALIDATION', true),
        'credential_rotation_days' => env('PLATFORM_CREDENTIAL_ROTATION_DAYS', 90),
        'max_retry_attempts' => env('PLATFORM_MAX_RETRY_ATTEMPTS', 3),
        'retry_delay_seconds' => env('PLATFORM_RETRY_DELAY_SECONDS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => env('PLATFORM_CIRCUIT_BREAKER_THRESHOLD', 5),
        'recovery_timeout' => env('PLATFORM_CIRCUIT_BREAKER_RECOVERY', 60), // seconds
        'half_open_max_calls' => env('PLATFORM_CIRCUIT_BREAKER_HALF_OPEN', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'log_requests' => env('PLATFORM_LOG_REQUESTS', true),
        'log_responses' => env('PLATFORM_LOG_RESPONSES', true),
        'log_credentials' => env('PLATFORM_LOG_CREDENTIALS', false), // Never log credentials in production
        'sensitive_fields' => [
            'password',
            'secret',
            'key',
            'token',
            'access_token',
            'refresh_token',
            'partner_key',
            'app_secret',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'default_interval' => env('PLATFORM_SYNC_INTERVAL', 3600), // 1 hour
        'max_orders_per_sync' => env('PLATFORM_MAX_ORDERS_PER_SYNC', 100),
        'sync_timeout' => env('PLATFORM_SYNC_TIMEOUT', 300), // 5 minutes
        'enable_real_time_sync' => env('PLATFORM_ENABLE_REAL_TIME_SYNC', true),
    ],
];