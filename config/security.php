<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security-related configuration options for the
    | Order Management System, following OWASP security best practices.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Password Policy Configuration
    |--------------------------------------------------------------------------
    */
    'password' => [
        'min_length' => env('SECURITY_PASSWORD_MIN_LENGTH', 12),
        'require_uppercase' => env('SECURITY_PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('SECURITY_PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('SECURITY_PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => env('SECURITY_PASSWORD_REQUIRE_SYMBOLS', true),
        'prevent_common' => env('SECURITY_PASSWORD_PREVENT_COMMON', true),
        'prevent_personal_info' => env('SECURITY_PASSWORD_PREVENT_PERSONAL_INFO', true),
        'history_count' => env('SECURITY_PASSWORD_HISTORY_COUNT', 5),
        'max_age_days' => env('SECURITY_PASSWORD_MAX_AGE_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Lockout Configuration
    |--------------------------------------------------------------------------
    */
    'lockout' => [
        'max_attempts' => env('SECURITY_LOCKOUT_MAX_ATTEMPTS', 5),
        'lockout_duration' => env('SECURITY_LOCKOUT_DURATION', 900), // 15 minutes
        'progressive_delay' => env('SECURITY_LOCKOUT_PROGRESSIVE_DELAY', true),
        'notify_admin' => env('SECURITY_LOCKOUT_NOTIFY_ADMIN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security Configuration
    |--------------------------------------------------------------------------
    */
    'session' => [
        'timeout_minutes' => env('SECURITY_SESSION_TIMEOUT', 60),
        'concurrent_sessions' => env('SECURITY_CONCURRENT_SESSIONS', 1),
        'require_fresh_auth' => env('SECURITY_REQUIRE_FRESH_AUTH', 30), // minutes
        'regenerate_on_login' => env('SECURITY_REGENERATE_ON_LOGIN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'login_attempts' => env('SECURITY_RATE_LIMIT_LOGIN', '5,1'), // 5 attempts per minute
        'api_requests' => env('SECURITY_RATE_LIMIT_API', '60,1'), // 60 requests per minute
        'password_reset' => env('SECURITY_RATE_LIMIT_PASSWORD_RESET', '3,60'), // 3 attempts per hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication Configuration
    |--------------------------------------------------------------------------
    */
    'two_factor' => [
        'enabled' => env('SECURITY_2FA_ENABLED', true),
        'required_for_admin' => env('SECURITY_2FA_REQUIRED_ADMIN', true),
        'backup_codes_count' => env('SECURITY_2FA_BACKUP_CODES', 8),
        'totp_window' => env('SECURITY_2FA_TOTP_WINDOW', 1),
        'remember_device_days' => env('SECURITY_2FA_REMEMBER_DEVICE', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers Configuration
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'hsts' => [
            'enabled' => env('SECURITY_HSTS_ENABLED', true),
            'max_age' => env('SECURITY_HSTS_MAX_AGE', 31536000), // 1 year
            'include_subdomains' => env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
            'preload' => env('SECURITY_HSTS_PRELOAD', true),
        ],
        'csp' => [
            'enabled' => env('SECURITY_CSP_ENABLED', true),
            'report_only' => env('SECURITY_CSP_REPORT_ONLY', false),
            'report_uri' => env('SECURITY_CSP_REPORT_URI'),
        ],
        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'DENY'),
        'content_type_options' => env('SECURITY_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'geolocation=(), microphone=(), camera=()'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging Configuration
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => env('SECURITY_AUDIT_ENABLED', true),
        'log_authentication' => env('SECURITY_AUDIT_AUTH', true),
        'log_authorization' => env('SECURITY_AUDIT_AUTHZ', true),
        'log_data_changes' => env('SECURITY_AUDIT_DATA_CHANGES', true),
        'log_admin_actions' => env('SECURITY_AUDIT_ADMIN_ACTIONS', true),
        'retention_days' => env('SECURITY_AUDIT_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Token Configuration
    |--------------------------------------------------------------------------
    */
    'api_tokens' => [
        'default_expiration' => env('SECURITY_API_TOKEN_EXPIRATION', 60), // minutes
        'max_tokens_per_user' => env('SECURITY_API_MAX_TOKENS_PER_USER', 5),
        'require_abilities' => env('SECURITY_API_REQUIRE_ABILITIES', true),
        'log_token_usage' => env('SECURITY_API_LOG_TOKEN_USAGE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Security Configuration
    |--------------------------------------------------------------------------
    */
    'cors' => [
        'allowed_origins' => explode(',', env('SECURITY_CORS_ALLOWED_ORIGINS', '')),
        'allowed_methods' => explode(',', env('SECURITY_CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS')),
        'allowed_headers' => explode(',', env('SECURITY_CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With')),
        'max_age' => env('SECURITY_CORS_MAX_AGE', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Validation Configuration
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'max_input_vars' => env('SECURITY_MAX_INPUT_VARS', 1000),
        'sanitize_html' => env('SECURITY_SANITIZE_HTML', true),
        'max_string_length' => env('SECURITY_MAX_STRING_LENGTH', 10000),
        'allowed_file_types' => explode(',', env('SECURITY_ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx')),
        'max_file_size' => env('SECURITY_MAX_FILE_SIZE', 10485760), // 10MB
    ],
];