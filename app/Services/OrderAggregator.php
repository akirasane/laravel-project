<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PlatformConfiguration;
use App\Services\PlatformConnectorFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class OrderAggregator
{
    private PlatformConnectorFactory $connectorFactory;
    private OrderNormalizer $normalizer;
    private OrderSyncManager $syncManager;

    public function __construct(
        PlatformConnectorFactory $connectorFactory,
        OrderNormalizer $normalizer,
        OrderSyncManager $syncManager
    ) {
        $this->connectorFactory = $connectorFactory;
        $this->normalizer = $normalizer;
        $this->syncManager = $syncManager;
    }

    /**
     * Aggregate orders from all active platforms.
     */
    public function aggregateOrders(Carbon $since = null): Collection
    {
        $allOrders = collect();
        $activePlatforms = $this->getActivePlatforms();

        Log::info('Starting order aggregation', [
            'platforms' => $activePlatforms->pluck('platform_type')->toArray(),
            'since' => $since?->toISOString()
        ]);

        foreach ($activePlatforms as $platformConfig) {
            try {
                $orders = $this->aggregateFromPlatform($platformConfig, $since);
                $allOrders = $allOrders->merge($orders);
                
                Log::info('Platform orders aggregated', [
                    'platform' => $platformConfig->platform_type,
                    'count' => $orders->count()
                ]);
            } catch (Exception $e) {
                Log::error('Failed to aggregate orders from platform', [
                    'platform' => $platformConfig->platform_type,
                    'error' => $e->getMessage()
                ]);
                
                // Update sync status to failed
                $this->syncManager->updateSyncStatus($platformConfig, 'failed', $e->getMessage());
            }
        }

        Log::info('Order aggregation completed', [
            'total_orders' => $allOrders->count()
        ]);

        return $allOrders;
    }

    /**
     * Aggregate orders from a specific platform.
     */
    public function aggregateFromPlatform(PlatformConfiguration $platformConfig, Carbon $since = null): Collection
    {
        $connector = $this->connectorFactory->create($platformConfig->platform_type);
        
        if (!$connector->authenticate($platformConfig->credentials)) {
            throw new Exception("Authentication failed for platform: {$platformConfig->platform_type}");
        }

        // Update sync status to in progress
        $this->syncManager->updateSyncStatus($platformConfig, 'in_progress');

        // Fetch raw orders from platform
        $rawOrders = $connector->fetchOrders($since);
        
        // Normalize orders to unified format
        $normalizedOrders = collect();
        foreach ($rawOrders as $rawOrder) {
            try {
                $normalizedOrder = $this->normalizer->normalize($rawOrder, $platformConfig->platform_type);
                $normalizedOrders->push($normalizedOrder);
            } catch (Exception $e) {
                Log::warning('Failed to normalize order', [
                    'platform' => $platformConfig->platform_type,
                    'order_id' => $rawOrder['platform_order_id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Update sync status to completed
        $this->syncManager->updateSyncStatus($platformConfig, 'completed');

        return $normalizedOrders;
    }

    /**
     * Aggregate orders from a specific platform type.
     */
    public function aggregateFromPlatformType(string $platformType, Carbon $since = null): Collection
    {
        $platformConfig = PlatformConfiguration::where('platform_type', $platformType)
            ->where('is_active', true)
            ->first();

        if (!$platformConfig) {
            throw new Exception("No active configuration found for platform: {$platformType}");
        }

        return $this->aggregateFromPlatform($platformConfig, $since);
    }

    /**
     * Get all active platform configurations.
     */
    private function getActivePlatforms(): Collection
    {
        return PlatformConfiguration::where('is_active', true)->get();
    }

    /**
     * Sync and store orders in database.
     */
    public function syncAndStoreOrders(Carbon $since = null): array
    {
        $aggregatedOrders = $this->aggregateOrders($since);
        $results = [
            'total_fetched' => $aggregatedOrders->count(),
            'stored' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        foreach ($aggregatedOrders as $orderData) {
            try {
                $result = $this->storeOrUpdateOrder($orderData);
                $results[$result]++;
            } catch (Exception $e) {
                $results['errors'][] = [
                    'platform_order_id' => $orderData['platform_order_id'] ?? 'unknown',
                    'platform_type' => $orderData['platform_type'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
                
                Log::error('Failed to store order', [
                    'order_data' => $orderData,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Order sync and store completed', $results);
        return $results;
    }

    /**
     * Store or update a single order.
     */
    private function storeOrUpdateOrder(array $orderData): string
    {
        $existingOrder = Order::where('platform_order_id', $orderData['platform_order_id'])
            ->where('platform_type', $orderData['platform_type'])
            ->first();

        if ($existingOrder) {
            // Check if order data has changed
            if ($this->hasOrderChanged($existingOrder, $orderData)) {
                $existingOrder->update($orderData);
                return 'updated';
            }
            return 'skipped';
        }

        // Create new order
        Order::create($orderData);
        return 'stored';
    }

    /**
     * Check if order data has changed.
     */
    private function hasOrderChanged(Order $existingOrder, array $newData): bool
    {
        $fieldsToCheck = [
            'status', 'total_amount', 'customer_name', 'customer_email', 
            'customer_phone', 'shipping_address', 'billing_address'
        ];

        foreach ($fieldsToCheck as $field) {
            if (isset($newData[$field]) && $existingOrder->$field !== $newData[$field]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get aggregation statistics.
     */
    public function getAggregationStats(): array
    {
        $platforms = $this->getActivePlatforms();
        $stats = [];

        foreach ($platforms as $platform) {
            $stats[$platform->platform_type] = [
                'last_sync' => $platform->last_sync?->toISOString(),
                'sync_status' => $platform->sync_status ?? 'never_synced',
                'total_orders' => Order::where('platform_type', $platform->platform_type)->count(),
                'recent_orders' => Order::where('platform_type', $platform->platform_type)
                    ->where('created_at', '>=', now()->subDay())
                    ->count()
            ];
        }

        return $stats;
    }
}