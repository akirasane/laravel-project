<?php

namespace App\Services;

use App\Contracts\PlatformConnectorInterface;
use App\Services\PlatformConnectors\ShopeeConnector;
use App\Services\PlatformConnectors\LazadaConnector;
use App\Services\PlatformConnectors\ShopifyConnector;
use App\Services\PlatformConnectors\TikTokConnector;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PlatformConnectorFactory
{
    private PlatformCredentialManager $credentialManager;
    private array $connectors = [];

    public function __construct(PlatformCredentialManager $credentialManager)
    {
        $this->credentialManager = $credentialManager;
    }

    /**
     * Create a platform connector instance.
     */
    public function create(string $platformType): PlatformConnectorInterface
    {
        // Return cached connector if available
        if (isset($this->connectors[$platformType])) {
            return $this->connectors[$platformType];
        }

        $connector = match ($platformType) {
            'shopee' => new ShopeeConnector($this->credentialManager),
            'lazada' => new LazadaConnector($this->credentialManager),
            'shopify' => new ShopifyConnector($this->credentialManager),
            'tiktok' => new TikTokConnector($this->credentialManager),
            default => throw new InvalidArgumentException("Unsupported platform type: {$platformType}")
        };

        // Cache the connector
        $this->connectors[$platformType] = $connector;

        Log::debug('Platform connector created', [
            'platform' => $platformType,
            'class' => get_class($connector)
        ]);

        return $connector;
    }

    /**
     * Get all available platform types.
     */
    public function getAvailablePlatforms(): array
    {
        return ['shopee', 'lazada', 'shopify', 'tiktok'];
    }

    /**
     * Check if a platform type is supported.
     */
    public function isSupported(string $platformType): bool
    {
        return in_array($platformType, $this->getAvailablePlatforms());
    }

    /**
     * Get configuration schema for a platform.
     */
    public function getConfigurationSchema(string $platformType): array
    {
        if (!$this->isSupported($platformType)) {
            throw new InvalidArgumentException("Unsupported platform type: {$platformType}");
        }

        $connector = $this->create($platformType);
        return $connector->getConfigurationSchema();
    }

    /**
     * Test connection for a platform.
     */
    public function testConnection(string $platformType): bool
    {
        try {
            $connector = $this->create($platformType);
            return $connector->testConnection();
        } catch (\Exception $e) {
            Log::error('Platform connection test failed', [
                'platform' => $platformType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get connectors for all configured platforms.
     */
    public function getConfiguredConnectors(): array
    {
        $configuredPlatforms = $this->credentialManager->getConfiguredPlatforms();
        $connectors = [];

        foreach ($configuredPlatforms as $platform) {
            try {
                $connectors[$platform] = $this->create($platform);
            } catch (\Exception $e) {
                Log::warning('Failed to create connector for configured platform', [
                    'platform' => $platform,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $connectors;
    }

    /**
     * Clear cached connectors.
     */
    public function clearCache(): void
    {
        $this->connectors = [];
        Log::debug('Platform connector cache cleared');
    }

    /**
     * Get connector statistics.
     */
    public function getStatistics(): array
    {
        return [
            'available_platforms' => count($this->getAvailablePlatforms()),
            'configured_platforms' => count($this->credentialManager->getConfiguredPlatforms()),
            'cached_connectors' => count($this->connectors),
            'supported_platforms' => $this->getAvailablePlatforms(),
            'configured_platforms_list' => $this->credentialManager->getConfiguredPlatforms(),
        ];
    }
}