<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ConfigurationValidationService
{
    /**
     * Required environment variables for the application.
     */
    private array $requiredVariables = [
        // Application
        'APP_NAME',
        'APP_ENV',
        'APP_KEY',
        'APP_URL',

        // Database
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',

        // Redis
        'REDIS_HOST',
        'REDIS_PORT',

        // Session
        'SESSION_DRIVER',
        'SESSION_LIFETIME',

        // Queue
        'QUEUE_CONNECTION',

        // Mail
        'MAIL_MAILER',
        'MAIL_FROM_ADDRESS',
        'MAIL_FROM_NAME',
    ];

    /**
     * Environment-specific required variables.
     */
    private array $environmentSpecificVariables = [
        'production' => [
            'SECURITY_HSTS_ENABLED',
            'SECURITY_CSP_ENABLED',
            'SESSION_ENCRYPT',
            'SESSION_SECURE_COOKIE',
            'BCRYPT_ROUNDS',
            'SENTRY_LARAVEL_DSN',
        ],
        'local' => [
            'APP_DEBUG',
        ],
    ];

    /**
     * Security-related environment variables.
     */
    private array $securityVariables = [
        'BCRYPT_ROUNDS',
        'SESSION_ENCRYPT',
        'SESSION_HTTP_ONLY',
        'SESSION_SAME_SITE',
        'SESSION_SECURE_COOKIE',
        'SECURITY_HSTS_ENABLED',
        'SECURITY_CSP_ENABLED',
        'SECURITY_FRAME_OPTIONS',
    ];

    /**
     * Validate all required environment variables.
     */
    public function validateConfiguration(): array
    {
        $errors = [];
        $warnings = [];

        // Check required variables
        $missingRequired = $this->checkRequiredVariables();
        if (!empty($missingRequired)) {
            $errors[] = 'Missing required environment variables: ' . implode(', ', $missingRequired);
        }

        // Check environment-specific variables
        $missingEnvironmentSpecific = $this->checkEnvironmentSpecificVariables();
        if (!empty($missingEnvironmentSpecific)) {
            $warnings[] = 'Missing environment-specific variables: ' . implode(', ', $missingEnvironmentSpecific);
        }

        // Validate security configuration
        $securityIssues = $this->validateSecurityConfiguration();
        $errors = array_merge($errors, $securityIssues['errors']);
        $warnings = array_merge($warnings, $securityIssues['warnings']);

        // Validate database configuration
        $databaseIssues = $this->validateDatabaseConfiguration();
        $errors = array_merge($errors, $databaseIssues['errors']);
        $warnings = array_merge($warnings, $databaseIssues['warnings']);

        // Validate platform API configuration
        $apiIssues = $this->validatePlatformApiConfiguration();
        $warnings = array_merge($warnings, $apiIssues['warnings']);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check for missing required environment variables.
     */
    private function checkRequiredVariables(): array
    {
        $missing = [];

        foreach ($this->requiredVariables as $variable) {
            if (empty(env($variable))) {
                $missing[] = $variable;
            }
        }

        return $missing;
    }

    /**
     * Check for missing environment-specific variables.
     */
    private function checkEnvironmentSpecificVariables(): array
    {
        $environment = env('APP_ENV', 'production');
        $missing = [];

        if (isset($this->environmentSpecificVariables[$environment])) {
            foreach ($this->environmentSpecificVariables[$environment] as $variable) {
                if (env($variable) === null) {
                    $missing[] = $variable;
                }
            }
        }

        return $missing;
    }

    /**
     * Validate security configuration settings.
     */
    private function validateSecurityConfiguration(): array
    {
        $errors = [];
        $warnings = [];
        $environment = env('APP_ENV', 'production');

        // Validate APP_KEY
        if (empty(env('APP_KEY'))) {
            $errors[] = 'APP_KEY is not set. Run "php artisan key:generate" to generate one.';
        }

        // Validate bcrypt rounds
        $bcryptRounds = (int) env('BCRYPT_ROUNDS', 10);
        if ($bcryptRounds < 10) {
            $warnings[] = 'BCRYPT_ROUNDS should be at least 10 for security.';
        }

        // Production-specific security checks
        if ($environment === 'production') {
            if (env('APP_DEBUG', false)) {
                $errors[] = 'APP_DEBUG should be false in production.';
            }

            if (!env('SESSION_ENCRYPT', false)) {
                $warnings[] = 'SESSION_ENCRYPT should be true in production.';
            }

            if (!env('SESSION_SECURE_COOKIE', false)) {
                $warnings[] = 'SESSION_SECURE_COOKIE should be true in production with HTTPS.';
            }

            if (env('SESSION_SAME_SITE') !== 'strict') {
                $warnings[] = 'SESSION_SAME_SITE should be "strict" in production.';
            }

            if (!env('SECURITY_HSTS_ENABLED', false)) {
                $warnings[] = 'SECURITY_HSTS_ENABLED should be true in production.';
            }

            if (!env('SECURITY_CSP_ENABLED', false)) {
                $warnings[] = 'SECURITY_CSP_ENABLED should be true in production.';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate database configuration.
     */
    private function validateDatabaseConfiguration(): array
    {
        $errors = [];
        $warnings = [];

        // Check database connection
        try {
            \DB::connection()->getPdo();
        } catch (\Exception $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }

        // Check Redis connection
        try {
            if (class_exists('\Redis') && extension_loaded('redis')) {
                \Illuminate\Support\Facades\Redis::connection()->ping();
            } else {
                $warnings[] = 'Redis PHP extension not installed - using array cache driver instead';
            }
        } catch (\Exception $e) {
            $warnings[] = 'Redis connection failed: ' . $e->getMessage();
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate platform API configuration.
     */
    private function validatePlatformApiConfiguration(): array
    {
        $warnings = [];
        
        $platforms = ['SHOPEE', 'LAZADA', 'SHOPIFY', 'TIKTOK'];
        
        foreach ($platforms as $platform) {
            $apiUrl = env("{$platform}_API_URL");
            $apiKey = env("{$platform}_API_KEY") ?? env("{$platform}_PARTNER_ID") ?? env("{$platform}_APP_KEY");
            
            if (empty($apiUrl) || empty($apiKey)) {
                $warnings[] = "Platform {$platform} API configuration is incomplete.";
            }
        }

        return ['warnings' => $warnings];
    }

    /**
     * Log configuration validation results.
     */
    public function logValidationResults(array $results): void
    {
        if (!$results['valid']) {
            Log::error('Configuration validation failed', [
                'errors' => $results['errors'],
                'warnings' => $results['warnings'],
            ]);
        } elseif (!empty($results['warnings'])) {
            Log::warning('Configuration validation completed with warnings', [
                'warnings' => $results['warnings'],
            ]);
        } else {
            Log::info('Configuration validation passed successfully');
        }
    }

    /**
     * Get configuration summary for monitoring.
     */
    public function getConfigurationSummary(): array
    {
        return [
            'environment' => env('APP_ENV'),
            'debug_mode' => env('APP_DEBUG', false),
            'database_connection' => env('DB_CONNECTION'),
            'cache_driver' => env('CACHE_STORE'),
            'session_driver' => env('SESSION_DRIVER'),
            'queue_connection' => env('QUEUE_CONNECTION'),
            'mail_driver' => env('MAIL_MAILER'),
            'security_headers_enabled' => env('SECURITY_CSP_ENABLED', false),
            'session_encryption' => env('SESSION_ENCRYPT', false),
            'bcrypt_rounds' => env('BCRYPT_ROUNDS', 10),
        ];
    }
}