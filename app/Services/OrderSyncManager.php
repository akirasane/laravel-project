<?php

namespace App\Services;

use App\Models\PlatformConfiguration;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class OrderSyncManager
{
    private OrderAggregator $aggregator;

    public function __construct(OrderAggregator $aggregator)
    {
        $this->aggregator = $aggregator;
    }

    /**
     * Schedule synchronization for all active platforms.
     */
    public function scheduleSync(): void
    {
        $platforms = PlatformConfiguration::where('is_active', true)->get();

        foreach ($platforms as $platform) {
            $this->schedulePlatformSync($platform);
        }
    }

    /**
     * Schedule synchronization for a specific platform.
     */
    public function schedulePlatformSync(PlatformConfiguration $platform): void
    {
        $syncInterval = $platform->sync_interval ?? 15; // Default 15 minutes
        $lastSync = $platform->last_sync;
        $nextSync = $lastSync ? $lastSync->addMinutes($syncInterval) : now();

        if (now()->gte($nextSync)) {
            $this->performSync($platform);
        } else {
            Log::debug('Platform sync not due yet', [
                'platform' => $platform->platform_type,
                'next_sync' => $nextSync->toISOString()
            ]);
        }
    }

    /**
     * Perform synchronization for a platform.
     */
    public function performSync(PlatformConfiguration $platform): array
    {
        $lockKey = "sync_lock_{$platform->platform_type}_{$platform->id}";
        
        // Prevent concurrent syncs for the same platform
        if (Cache::has($lockKey)) {
            Log::info('Sync already in progress for platform', [
                'platform' => $platform->platform_type
            ]);
            return ['status' => 'already_in_progress'];
        }

        // Set sync lock (expires in 30 minutes)
        Cache::put($lockKey, true, 1800);

        try {
            Log::info('Starting scheduled sync', [
                'platform' => $platform->platform_type,
                'last_sync' => $platform->last_sync?->toISOString()
            ]);

            $this->updateSyncStatus($platform, 'in_progress');

            // Determine sync window
            $since = $this->determineSyncWindow($platform);
            
            // Perform aggregation and storage
            $results = $this->aggregator->syncAndStoreOrders($since);

            // Update platform sync metadata
            $this->updateSyncMetadata($platform, $results);

            $this->updateSyncStatus($platform, 'completed');

            Log::info('Scheduled sync completed', [
                'platform' => $platform->platform_type,
                'results' => $results
            ]);

            return array_merge(['status' => 'completed'], $results);

        } catch (Exception $e) {
            $this->updateSyncStatus($platform, 'failed', $e->getMessage());
            
            Log::error('Scheduled sync failed', [
                'platform' => $platform->platform_type,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * Update sync status for a platform.
     */
    public function updateSyncStatus(PlatformConfiguration $platform, string $status, string $errorMessage = null): void
    {
        $platform->update([
            'sync_status' => $status,
            'sync_error_message' => $errorMessage,
            'last_sync_attempt' => now()
        ]);

        if ($status === 'completed') {
            $platform->update(['last_sync' => now()]);
        }
    }

    /**
     * Determine the sync window based on last sync time.
     */
    private function determineSyncWindow(PlatformConfiguration $platform): ?Carbon
    {
        if (!$platform->last_sync) {
            // First sync - get orders from last 7 days
            return now()->subDays(7);
        }

        // Incremental sync - get orders since last successful sync
        return $platform->last_sync->subMinutes(5); // 5-minute overlap for safety
    }

    /**
     * Update sync metadata after successful sync.
     */
    private function updateSyncMetadata(PlatformConfiguration $platform, array $results): void
    {
        $metadata = $platform->sync_metadata ?? [];
        
        $metadata['last_sync_results'] = $results;
        $metadata['sync_history'] = array_slice(
            array_merge([$metadata['sync_history'] ?? []], [[
                'timestamp' => now()->toISOString(),
                'results' => $results
            ]]),
            -10 // Keep last 10 sync results
        );

        $platform->update(['sync_metadata' => $metadata]);
    }

    /**
     * Get sync status for all platforms.
     */
    public function getSyncStatus(): array
    {
        $platforms = PlatformConfiguration::where('is_active', true)->get();
        $status = [];

        foreach ($platforms as $platform) {
            $status[$platform->platform_type] = [
                'sync_status' => $platform->sync_status ?? 'never_synced',
                'last_sync' => $platform->last_sync?->toISOString(),
                'last_sync_attempt' => $platform->last_sync_attempt?->toISOString(),
                'sync_error_message' => $platform->sync_error_message,
                'next_sync_due' => $this->getNextSyncTime($platform)?->toISOString(),
                'orders_count' => Order::where('platform_type', $platform->platform_type)->count()
            ];
        }

        return $status;
    }

    /**
     * Get next sync time for a platform.
     */
    private function getNextSyncTime(PlatformConfiguration $platform): ?Carbon
    {
        if (!$platform->last_sync) {
            return now(); // Sync immediately if never synced
        }

        $syncInterval = $platform->sync_interval ?? 15;
        return $platform->last_sync->addMinutes($syncInterval);
    }

    /**
     * Force sync for a specific platform.
     */
    public function forceSync(string $platformType): array
    {
        $platform = PlatformConfiguration::where('platform_type', $platformType)
            ->where('is_active', true)
            ->first();

        if (!$platform) {
            throw new Exception("No active configuration found for platform: {$platformType}");
        }

        Log::info('Force sync initiated', ['platform' => $platformType]);
        
        return $this->performSync($platform);
    }

    /**
     * Sync orders for a specific date range.
     */
    public function syncDateRange(string $platformType, Carbon $startDate, Carbon $endDate): array
    {
        $platform = PlatformConfiguration::where('platform_type', $platformType)
            ->where('is_active', true)
            ->first();

        if (!$platform) {
            throw new Exception("No active configuration found for platform: {$platformType}");
        }

        Log::info('Date range sync initiated', [
            'platform' => $platformType,
            'start_date' => $startDate->toISOString(),
            'end_date' => $endDate->toISOString()
        ]);

        try {
            $this->updateSyncStatus($platform, 'in_progress');

            // Fetch orders for the specific date range
            $orders = $this->aggregator->aggregateFromPlatform($platform, $startDate);
            
            // Filter orders within the date range
            $filteredOrders = $orders->filter(function ($order) use ($startDate, $endDate) {
                $orderDate = Carbon::parse($order['order_date']);
                return $orderDate->between($startDate, $endDate);
            });

            // Store filtered orders
            $results = [
                'total_fetched' => $filteredOrders->count(),
                'stored' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => []
            ];

            foreach ($filteredOrders as $orderData) {
                try {
                    $result = $this->storeOrUpdateOrder($orderData);
                    $results[$result]++;
                } catch (Exception $e) {
                    $results['errors'][] = [
                        'platform_order_id' => $orderData['platform_order_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->updateSyncStatus($platform, 'completed');

            Log::info('Date range sync completed', [
                'platform' => $platformType,
                'results' => $results
            ]);

            return array_merge(['status' => 'completed'], $results);

        } catch (Exception $e) {
            $this->updateSyncStatus($platform, 'failed', $e->getMessage());
            
            Log::error('Date range sync failed', [
                'platform' => $platformType,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Store or update a single order (helper method).
     */
    private function storeOrUpdateOrder(array $orderData): string
    {
        $existingOrder = Order::where('platform_order_id', $orderData['platform_order_id'])
            ->where('platform_type', $orderData['platform_type'])
            ->first();

        if ($existingOrder) {
            $fieldsToCheck = [
                'status', 'total_amount', 'customer_name', 'customer_email', 
                'customer_phone', 'shipping_address', 'billing_address'
            ];

            $hasChanged = false;
            foreach ($fieldsToCheck as $field) {
                if (isset($orderData[$field]) && $existingOrder->$field !== $orderData[$field]) {
                    $hasChanged = true;
                    break;
                }
            }

            if ($hasChanged) {
                $existingOrder->update($orderData);
                return 'updated';
            }
            return 'skipped';
        }

        Order::create($orderData);
        return 'stored';
    }

    /**
     * Get sync statistics.
     */
    public function getSyncStatistics(): array
    {
        $platforms = PlatformConfiguration::where('is_active', true)->get();
        $stats = [
            'total_platforms' => $platforms->count(),
            'platforms_synced_today' => 0,
            'total_orders_synced_today' => 0,
            'platforms' => []
        ];

        foreach ($platforms as $platform) {
            $todayOrders = Order::where('platform_type', $platform->platform_type)
                ->whereDate('created_at', today())
                ->count();

            $platformStats = [
                'total_orders' => Order::where('platform_type', $platform->platform_type)->count(),
                'orders_today' => $todayOrders,
                'last_sync' => $platform->last_sync?->toISOString(),
                'sync_status' => $platform->sync_status ?? 'never_synced'
            ];

            if ($platform->last_sync && $platform->last_sync->isToday()) {
                $stats['platforms_synced_today']++;
            }

            $stats['total_orders_synced_today'] += $todayOrders;
            $stats['platforms'][$platform->platform_type] = $platformStats;
        }

        return $stats;
    }
}