<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Headers Configuration
    |--------------------------------------------------------------------------
    |
    | Configure security headers for OWASP compliance and protection against
    | common web vulnerabilities.
    |
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
            'policy' => env('SECURITY_CSP_POLICY', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' ws: wss:; frame-ancestors 'none';"),
        ],

        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'DENY'),
        'content_type_options' => env('SECURITY_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'geolocation=(), microphone=(), camera=()'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Cross-Origin Resource Sharing (CORS) settings for API endpoints.
    |
    */

    'cors' => [
        'allowed_origins' => array_filter(explode(',', env('SECURITY_CORS_ALLOWED_ORIGINS', ''))),
        'allowed_methods' => explode(',', env('SECURITY_CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS')),
        'allowed_headers' => explode(',', env('SECURITY_CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With')),
        'exposed_headers' => explode(',', env('SECURITY_CORS_EXPOSED_HEADERS', '')),
        'max_age' => env('SECURITY_CORS_MAX_AGE', 86400),
        'supports_credentials' => env('SECURITY_CORS_SUPPORTS_CREDENTIALS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Validation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure input validation and sanitization settings.
    |
    */

    'validation' => [
        'max_input_vars' => env('SECURITY_MAX_INPUT_VARS', 1000),
        'max_file_size' => env('SECURITY_MAX_FILE_SIZE', 10240), // KB
        'allowed_file_types' => explode(',', env('SECURITY_ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx')),
        'sanitize_html' => env('SECURITY_SANITIZE_HTML', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for API endpoints and authentication attempts.
    |
    */

    'rate_limiting' => [
        'api' => [
            'max_attempts' => env('SECURITY_API_RATE_LIMIT', 60),
            'decay_minutes' => env('SECURITY_API_RATE_DECAY', 1),
        ],
        'auth' => [
            'max_attempts' => env('SECURITY_AUTH_RATE_LIMIT', 5),
            'decay_minutes' => env('SECURITY_AUTH_RATE_DECAY', 15),
        ],
        'password_reset' => [
            'max_attempts' => env('SECURITY_PASSWORD_RESET_RATE_LIMIT', 3),
            'decay_minutes' => env('SECURITY_PASSWORD_RESET_RATE_DECAY', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Encryption Configuration
    |--------------------------------------------------------------------------
    |
    | Configure encryption settings for sensitive database fields.
    |
    */

    'encryption' => [
        'key' => env('SECURITY_ENCRYPTION_KEY', env('APP_KEY')),
        'cipher' => env('SECURITY_ENCRYPTION_CIPHER', 'AES-256-CBC'),
        'sensitive_fields' => [
            'api_credentials',
            'access_tokens',
            'refresh_tokens',
            'customer_phone',
            'customer_email',
            'billing_address',
            'shipping_address',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security Configuration
    |--------------------------------------------------------------------------
    |
    | Additional session security settings beyond Laravel's default configuration.
    |
    */

    'session' => [
        'regenerate_on_login' => env('SECURITY_SESSION_REGENERATE_ON_LOGIN', true),
        'invalidate_on_password_change' => env('SECURITY_SESSION_INVALIDATE_ON_PASSWORD_CHANGE', true),
        'concurrent_sessions' => env('SECURITY_CONCURRENT_SESSIONS', 1),
        'idle_timeout' => env('SECURITY_SESSION_IDLE_TIMEOUT', 1800), // 30 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure password strength requirements and policies.
    |
    */

    'password_policy' => [
        'min_length' => env('SECURITY_PASSWORD_MIN_LENGTH', 12),
        'require_uppercase' => env('SECURITY_PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('SECURITY_PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('SECURITY_PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => env('SECURITY_PASSWORD_REQUIRE_SYMBOLS', true),
        'prevent_common_passwords' => env('SECURITY_PASSWORD_PREVENT_COMMON', true),
        'prevent_personal_info' => env('SECURITY_PASSWORD_PREVENT_PERSONAL', true),
        'history_count' => env('SECURITY_PASSWORD_HISTORY_COUNT', 5),
        'expiry_days' => env('SECURITY_PASSWORD_EXPIRY_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Lockout Configuration
    |--------------------------------------------------------------------------
    |
    | Configure account lockout mechanisms for failed authentication attempts.
    |
    */

    'account_lockout' => [
        'enabled' => env('SECURITY_ACCOUNT_LOCKOUT_ENABLED', true),
        'max_attempts' => env('SECURITY_ACCOUNT_LOCKOUT_MAX_ATTEMPTS', 5),
        'lockout_duration' => env('SECURITY_ACCOUNT_LOCKOUT_DURATION', 900), // 15 minutes
        'progressive_delay' => env('SECURITY_ACCOUNT_LOCKOUT_PROGRESSIVE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Trail Configuration
    |--------------------------------------------------------------------------
    |
    | Configure audit logging for security events and data changes.
    |
    */

    'audit' => [
        'enabled' => env('SECURITY_AUDIT_ENABLED', true),
        'log_channel' => env('SECURITY_AUDIT_LOG_CHANNEL', 'audit'),
        'events' => [
            'authentication' => env('SECURITY_AUDIT_AUTH', true),
            'authorization' => env('SECURITY_AUDIT_AUTHZ', true),
            'data_changes' => env('SECURITY_AUDIT_DATA_CHANGES', true),
            'admin_actions' => env('SECURITY_AUDIT_ADMIN_ACTIONS', true),
            'api_calls' => env('SECURITY_AUDIT_API_CALLS', true),
        ],
        'retention_days' => env('SECURITY_AUDIT_RETENTION_DAYS', 365),
    ],

];