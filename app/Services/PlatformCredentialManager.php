<?php

namespace App\Services;

use App\Models\PlatformConfiguration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class PlatformCredentialManager
{
    private const CACHE_TTL = 300; // 5 minutes
    private const ENCRYPTION_ALGORITHM = 'AES-256-GCM';

    /**
     * Store encrypted credentials for a platform.
     */
    public function storeCredentials(string $platformType, array $credentials): bool
    {
        try {
            $this->validateCredentials($platformType, $credentials);
            
            // Sanitize credentials before encryption
            $sanitizedCredentials = $this->sanitizeCredentials($credentials);
            
            // Store in database with Laravel's encryption
            $config = PlatformConfiguration::updateOrCreate(
                ['platform_type' => $platformType],
                [
                    'credentials' => $sanitizedCredentials,
                    'is_active' => true,
                    'sync_interval' => $credentials['sync_interval'] ?? 3600,
                ]
            );

            // Clear cache
            $this->clearCredentialsCache($platformType);

            Log::info('Platform credentials stored', [
                'platform' => $platformType,
                'config_id' => $config->id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to store platform credentials', [
                'platform' => $platformType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Retrieve decrypted credentials for a platform.
     */
    public function getCredentials(string $platformType): ?array
    {
        $cacheKey = "platform_credentials_{$platformType}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($platformType) {
            $config = PlatformConfiguration::where('platform_type', $platformType)
                ->where('is_active', true)
                ->first();

            if (!$config || !$config->credentials) {
                return null;
            }

            return $config->credentials;
        });
    }

    /**
     * Validate credentials structure for a specific platform.
     */
    private function validateCredentials(string $platformType, array $credentials): void
    {
        $requiredFields = $this->getRequiredFields($platformType);
        
        foreach ($requiredFields as $field) {
            if (!isset($credentials[$field]) || empty($credentials[$field])) {
                throw new InvalidArgumentException("Missing required credential field: {$field}");
            }
        }

        // Validate credential format
        $this->validateCredentialFormat($platformType, $credentials);
    }

    /**
     * Get required credential fields for each platform.
     */
    private function getRequiredFields(string $platformType): array
    {
        return match ($platformType) {
            'shopee' => ['partner_id', 'partner_key', 'shop_id'],
            'lazada' => ['app_key', 'app_secret', 'access_token'],
            'shopify' => ['shop_domain', 'access_token', 'api_key'],
            'tiktok' => ['app_key', 'app_secret', 'access_token', 'shop_id'],
            default => throw new InvalidArgumentException("Unsupported platform: {$platformType}")
        };
    }

    /**
     * Validate credential format and values.
     */
    private function validateCredentialFormat(string $platformType, array $credentials): void
    {
        switch ($platformType) {
            case 'shopee':
                if (!is_numeric($credentials['partner_id'])) {
                    throw new InvalidArgumentException('Shopee partner_id must be numeric');
                }
                if (strlen($credentials['partner_key']) < 32) {
                    throw new InvalidArgumentException('Shopee partner_key appears to be invalid');
                }
                break;

            case 'shopify':
                if (!filter_var("https://{$credentials['shop_domain']}", FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException('Invalid Shopify shop domain');
                }
                if (!str_starts_with($credentials['access_token'], 'shpat_')) {
                    throw new InvalidArgumentException('Invalid Shopify access token format');
                }
                break;

            case 'lazada':
            case 'tiktok':
                if (strlen($credentials['app_key']) < 16) {
                    throw new InvalidArgumentException('App key appears to be too short');
                }
                if (strlen($credentials['app_secret']) < 32) {
                    throw new InvalidArgumentException('App secret appears to be too short');
                }
                break;
        }
    }

    /**
     * Sanitize credentials to remove potentially harmful content.
     */
    private function sanitizeCredentials(array $credentials): array
    {
        $sanitized = [];
        
        foreach ($credentials as $key => $value) {
            if (is_string($value)) {
                // Remove any potential script tags or dangerous characters
                $sanitized[$key] = strip_tags(trim($value));
                
                // Additional validation for URLs
                if (str_contains($key, 'url') || str_contains($key, 'domain')) {
                    $sanitized[$key] = filter_var($sanitized[$key], FILTER_SANITIZE_URL);
                }
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Clear cached credentials for a platform.
     */
    public function clearCredentialsCache(string $platformType): void
    {
        Cache::forget("platform_credentials_{$platformType}");
    }

    /**
     * Rotate credentials for a platform.
     */
    public function rotateCredentials(string $platformType, array $newCredentials): bool
    {
        try {
            // Store old credentials as backup
            $oldCredentials = $this->getCredentials($platformType);
            
            if ($oldCredentials) {
                Cache::put("platform_credentials_backup_{$platformType}", $oldCredentials, 3600);
            }

            // Store new credentials
            $result = $this->storeCredentials($platformType, $newCredentials);

            if ($result) {
                Log::info('Platform credentials rotated', [
                    'platform' => $platformType
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to rotate platform credentials', [
                'platform' => $platformType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if credentials exist for a platform.
     */
    public function hasCredentials(string $platformType): bool
    {
        return $this->getCredentials($platformType) !== null;
    }

    /**
     * Get all configured platforms.
     */
    public function getConfiguredPlatforms(): array
    {
        return PlatformConfiguration::where('is_active', true)
            ->pluck('platform_type')
            ->toArray();
    }
}