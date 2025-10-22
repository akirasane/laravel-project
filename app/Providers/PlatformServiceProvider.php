<?php

namespace App\Providers;

use App\Services\PlatformCredentialManager;
use App\Services\SsrfProtectionService;
use App\Services\CircuitBreakerService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class PlatformServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register PlatformCredentialManager as singleton
        $this->app->singleton(PlatformCredentialManager::class, function ($app) {
            return new PlatformCredentialManager();
        });

        // Register SsrfProtectionService as singleton
        $this->app->singleton(SsrfProtectionService::class, function ($app) {
            return new SsrfProtectionService();
        });

        // Register CircuitBreakerService factory
        $this->app->bind(CircuitBreakerService::class, function ($app, $parameters) {
            $serviceName = $parameters['serviceName'] ?? 'default';
            return new CircuitBreakerService($serviceName);
        });

        // Register additional platform services
        $this->app->singleton(\App\Services\PlatformConnectorFactory::class);
        $this->app->singleton(\App\Services\PlatformSecurityMonitoringService::class);
        $this->app->singleton(\App\Services\PlatformValidationService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Validate platform configuration on boot
        $this->validatePlatformConfiguration();

        // Register platform logging channel if not already configured
        $this->configurePlatformLogging();
    }

    /**
     * Validate platform configuration.
     */
    private function validatePlatformConfiguration(): void
    {
        $requiredConfigs = [
            'platforms.request_timeout',
            'platforms.max_request_size',
            'platforms.rate_limits',
            'platforms.endpoints',
            'platforms.security',
        ];

        foreach ($requiredConfigs as $config) {
            if (config($config) === null) {
                Log::warning("Platform configuration missing: {$config}");
            }
        }

        // Validate rate limits are properly configured
        $platforms = ['shopee', 'lazada', 'shopify', 'tiktok'];
        foreach ($platforms as $platform) {
            $rateLimit = config("platforms.rate_limits.{$platform}.requests_per_minute");
            if (!$rateLimit || $rateLimit <= 0) {
                Log::warning("Invalid rate limit configuration for platform: {$platform}");
            }
        }
    }

    /**
     * Configure platform-specific logging.
     */
    private function configurePlatformLogging(): void
    {
        // Ensure platform_api log channel exists
        if (!config('logging.channels.platform_api')) {
            config([
                'logging.channels.platform_api' => [
                    'driver' => 'daily',
                    'path' => storage_path('logs/platform-api.log'),
                    'level' => env('LOG_LEVEL', 'info'),
                    'days' => env('LOG_DAILY_DAYS', 30),
                    'replace_placeholders' => true,
                ]
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            PlatformCredentialManager::class,
            SsrfProtectionService::class,
            CircuitBreakerService::class,
        ];
    }
}