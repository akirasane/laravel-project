<?php

namespace App\Providers;

use App\Services\ConfigurationValidationService;
use App\Services\AuditTrailService;
use App\Services\SecurityMonitoringService;
use App\Services\LogIntegrityService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ConfigurationValidationService::class);
        $this->app->singleton(AuditTrailService::class);
        $this->app->singleton(SecurityMonitoringService::class);
        $this->app->singleton(LogIntegrityService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register custom validation rules for security
        $this->registerSecurityValidationRules();

        // Validate configuration on application boot
        if (!$this->app->runningInConsole() || $this->app->runningUnitTests()) {
            $this->validateConfiguration();
        }
    }

    /**
     * Register custom validation rules for security.
     */
    private function registerSecurityValidationRules(): void
    {
        // Strong password validation rule
        Validator::extend('strong_password', function ($attribute, $value, $parameters, $validator) {
            $config = config('security.password_policy');
            
            // Check minimum length
            if (strlen($value) < $config['min_length']) {
                return false;
            }

            // Check for uppercase letters
            if ($config['require_uppercase'] && !preg_match('/[A-Z]/', $value)) {
                return false;
            }

            // Check for lowercase letters
            if ($config['require_lowercase'] && !preg_match('/[a-z]/', $value)) {
                return false;
            }

            // Check for numbers
            if ($config['require_numbers'] && !preg_match('/\d/', $value)) {
                return false;
            }

            // Check for symbols
            if ($config['require_symbols'] && !preg_match('/[^A-Za-z0-9]/', $value)) {
                return false;
            }

            // Check against common passwords
            if ($config['prevent_common_passwords']) {
                $commonPasswords = [
                    'password', '123456', '123456789', 'qwerty', 'abc123',
                    'password123', 'admin', 'letmein', 'welcome', 'monkey'
                ];
                
                if (in_array(strtolower($value), $commonPasswords)) {
                    return false;
                }
            }

            return true;
        });

        // Safe file upload validation rule
        Validator::extend('safe_file', function ($attribute, $value, $parameters, $validator) {
            if (!$value || !$value->isValid()) {
                return false;
            }

            $config = config('security.validation');
            
            // Check file size
            if ($value->getSize() > ($config['max_file_size'] * 1024)) {
                return false;
            }

            // Check file extension
            $extension = strtolower($value->getClientOriginalExtension());
            if (!in_array($extension, $config['allowed_file_types'])) {
                return false;
            }

            // Additional security checks for executable files
            $dangerousExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar', 'php', 'asp'];
            if (in_array($extension, $dangerousExtensions)) {
                return false;
            }

            return true;
        });

        // Sanitized input validation rule
        Validator::extend('sanitized', function ($attribute, $value, $parameters, $validator) {
            if (!is_string($value)) {
                return true; // Only validate strings
            }

            // Check for potentially dangerous patterns
            $dangerousPatterns = [
                '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
                '/javascript:/i',
                '/on\w+\s*=/i',
                '/<iframe/i',
                '/<object/i',
                '/<embed/i',
                '/<form/i',
            ];

            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Validate application configuration.
     */
    private function validateConfiguration(): void
    {
        try {
            $validator = $this->app->make(ConfigurationValidationService::class);
            $results = $validator->validateConfiguration();
            $validator->logValidationResults($results);

            if (!$results['valid']) {
                // In production, we might want to handle this differently
                if (config('app.env') === 'production') {
                    \Log::critical('Application started with invalid configuration', $results);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Configuration validation failed with exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}