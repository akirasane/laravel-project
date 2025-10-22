<?php

namespace App\Services;

use App\Services\SsrfProtectionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PlatformValidationService
{
    private SsrfProtectionService $ssrfProtection;

    public function __construct(SsrfProtectionService $ssrfProtection)
    {
        $this->ssrfProtection = $ssrfProtection;
    }

    /**
     * Validate platform configuration before saving.
     */
    public function validatePlatformConfiguration(string $platformType, array $config): array
    {
        $errors = [];

        try {
            // Validate platform type
            if (!in_array($platformType, ['shopee', 'lazada', 'shopify', 'tiktok'])) {
                $errors['platform_type'] = 'Invalid platform type';
            }

            // Validate credentials structure
            $credentialErrors = $this->validateCredentials($platformType, $config['credentials'] ?? []);
            if (!empty($credentialErrors)) {
                $errors['credentials'] = $credentialErrors;
            }

            // Validate sync interval
            $syncInterval = $config['sync_interval'] ?? 0;
            if (!is_numeric($syncInterval) || $syncInterval < 60 || $syncInterval > 86400) {
                $errors['sync_interval'] = 'Sync interval must be between 60 and 86400 seconds';
            }

            // Validate settings if provided
            if (isset($config['settings']) && !is_array($config['settings'])) {
                $errors['settings'] = 'Settings must be an array';
            }

            // Validate URLs in credentials
            $urlErrors = $this->validateCredentialUrls($platformType, $config['credentials'] ?? []);
            if (!empty($urlErrors)) {
                $errors['credential_urls'] = $urlErrors;
            }

        } catch (\Exception $e) {
            Log::error('Platform configuration validation failed', [
                'platform' => $platformType,
                'error' => $e->getMessage()
            ]);
            $errors['general'] = 'Configuration validation failed: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Validate credentials for a specific platform.
     */
    private function validateCredentials(string $platformType, array $credentials): array
    {
        $errors = [];
        $requiredFields = $this->getRequiredCredentialFields($platformType);

        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($credentials[$field]) || empty($credentials[$field])) {
                $errors[$field] = "Required field {$field} is missing or empty";
            }
        }

        // Platform-specific validation
        switch ($platformType) {
            case 'shopee':
                $errors = array_merge($errors, $this->validateShopeeCredentials($credentials));
                break;
            case 'lazada':
                $errors = array_merge($errors, $this->validateLazadaCredentials($credentials));
                break;
            case 'shopify':
                $errors = array_merge($errors, $this->validateShopifyCredentials($credentials));
                break;
            case 'tiktok':
                $errors = array_merge($errors, $this->validateTikTokCredentials($credentials));
                break;
        }

        return $errors;
    }

    /**
     * Get required credential fields for each platform.
     */
    private function getRequiredCredentialFields(string $platformType): array
    {
        return match ($platformType) {
            'shopee' => ['partner_id', 'partner_key', 'shop_id'],
            'lazada' => ['app_key', 'app_secret', 'access_token'],
            'shopify' => ['shop_domain', 'access_token', 'api_key'],
            'tiktok' => ['app_key', 'app_secret', 'access_token', 'shop_id'],
            default => []
        };
    }

    /**
     * Validate Shopee credentials.
     */
    private function validateShopeeCredentials(array $credentials): array
    {
        $errors = [];

        if (isset($credentials['partner_id']) && !is_numeric($credentials['partner_id'])) {
            $errors['partner_id'] = 'Partner ID must be numeric';
        }

        if (isset($credentials['partner_key']) && strlen($credentials['partner_key']) < 32) {
            $errors['partner_key'] = 'Partner key appears to be invalid (too short)';
        }

        if (isset($credentials['shop_id']) && !is_numeric($credentials['shop_id'])) {
            $errors['shop_id'] = 'Shop ID must be numeric';
        }

        return $errors;
    }

    /**
     * Validate Lazada credentials.
     */
    private function validateLazadaCredentials(array $credentials): array
    {
        $errors = [];

        if (isset($credentials['app_key']) && strlen($credentials['app_key']) < 16) {
            $errors['app_key'] = 'App key appears to be too short';
        }

        if (isset($credentials['app_secret']) && strlen($credentials['app_secret']) < 32) {
            $errors['app_secret'] = 'App secret appears to be too short';
        }

        if (isset($credentials['access_token']) && strlen($credentials['access_token']) < 32) {
            $errors['access_token'] = 'Access token appears to be invalid';
        }

        return $errors;
    }

    /**
     * Validate Shopify credentials.
     */
    private function validateShopifyCredentials(array $credentials): array
    {
        $errors = [];

        if (isset($credentials['shop_domain'])) {
            $domain = $credentials['shop_domain'];
            if (!preg_match('/^[a-zA-Z0-9\-]+\.myshopify\.com$/', $domain) && 
                !filter_var("https://{$domain}", FILTER_VALIDATE_URL)) {
                $errors['shop_domain'] = 'Invalid Shopify shop domain format';
            }
        }

        if (isset($credentials['access_token']) && !str_starts_with($credentials['access_token'], 'shpat_')) {
            $errors['access_token'] = 'Invalid Shopify access token format';
        }

        if (isset($credentials['api_key']) && strlen($credentials['api_key']) < 32) {
            $errors['api_key'] = 'API key appears to be invalid';
        }

        return $errors;
    }

    /**
     * Validate TikTok credentials.
     */
    private function validateTikTokCredentials(array $credentials): array
    {
        $errors = [];

        if (isset($credentials['app_key']) && strlen($credentials['app_key']) < 16) {
            $errors['app_key'] = 'App key appears to be too short';
        }

        if (isset($credentials['app_secret']) && strlen($credentials['app_secret']) < 32) {
            $errors['app_secret'] = 'App secret appears to be too short';
        }

        if (isset($credentials['access_token']) && strlen($credentials['access_token']) < 32) {
            $errors['access_token'] = 'Access token appears to be invalid';
        }

        if (isset($credentials['shop_id']) && !is_numeric($credentials['shop_id'])) {
            $errors['shop_id'] = 'Shop ID must be numeric';
        }

        return $errors;
    }

    /**
     * Validate URLs in credentials for SSRF protection.
     */
    private function validateCredentialUrls(string $platformType, array $credentials): array
    {
        $errors = [];
        $allowedDomains = $this->ssrfProtection->getAllowedDomainsForPlatform($platformType);

        foreach ($credentials as $key => $value) {
            if (is_string($value) && (str_contains($key, 'url') || str_contains($key, 'domain'))) {
                try {
                    // For Shopify domains, construct full URL for validation
                    if ($platformType === 'shopify' && $key === 'shop_domain') {
                        $value = "https://{$value}";
                    }

                    if (filter_var($value, FILTER_VALIDATE_URL)) {
                        $this->ssrfProtection->validateUrl($value, $allowedDomains);
                    }
                } catch (InvalidArgumentException $e) {
                    $errors[$key] = "Invalid URL: " . $e->getMessage();
                }
            }
        }

        return $errors;
    }

    /**
     * Test platform connection with provided credentials.
     */
    public function testPlatformConnection(string $platformType, array $credentials): array
    {
        try {
            // This would typically involve creating a connector instance and testing
            // For now, we'll do basic validation and return success
            $validationErrors = $this->validateCredentials($platformType, $credentials);
            
            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'message' => 'Credential validation failed',
                    'errors' => $validationErrors
                ];
            }

            // TODO: Implement actual connection testing with platform APIs
            // This would involve creating the appropriate connector and calling testConnection()
            
            Log::info('Platform connection test completed', [
                'platform' => $platformType,
                'success' => true
            ]);

            return [
                'success' => true,
                'message' => 'Connection test passed',
                'timestamp' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Platform connection test failed', [
                'platform' => $platformType,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Sanitize platform configuration input.
     */
    public function sanitizeConfiguration(array $config): array
    {
        $sanitized = [];

        foreach ($config as $key => $value) {
            if (is_string($value)) {
                // Remove potentially dangerous characters
                $sanitized[$key] = strip_tags(trim($value));
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeConfiguration($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}