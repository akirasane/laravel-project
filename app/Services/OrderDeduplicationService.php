<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class OrderDeduplicationService
{
    private OrderConflictResolver $conflictResolver;

    public function __construct(OrderConflictResolver $conflictResolver)
    {
        $this->conflictResolver = $conflictResolver;
    }

    /**
     * Detect and resolve duplicate orders across platforms.
     */
    public function detectAndResolveDuplicates(Collection $orders): array
    {
        $results = [
            'total_orders' => $orders->count(),
            'duplicates_found' => 0,
            'duplicates_resolved' => 0,
            'conflicts_detected' => 0,
            'errors' => []
        ];

        Log::info('Starting duplicate detection', [
            'order_count' => $orders->count()
        ]);

        // Group orders by potential duplicate criteria
        $duplicateGroups = $this->groupPotentialDuplicates($orders);

        foreach ($duplicateGroups as $group) {
            if ($group->count() > 1) {
                $results['duplicates_found']++;
                
                try {
                    $resolution = $this->resolveDuplicateGroup($group);
                    
                    if ($resolution['resolved']) {
                        $results['duplicates_resolved']++;
                    }
                    
                    if ($resolution['conflicts']) {
                        $results['conflicts_detected']++;
                    }
                } catch (Exception $e) {
                    $results['errors'][] = [
                        'group_id' => $this->generateGroupId($group),
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Failed to resolve duplicate group', [
                        'group_orders' => $group->pluck('platform_order_id')->toArray(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        Log::info('Duplicate detection completed', $results);
        return $results;
    }

    /**
     * Group orders that are potential duplicates.
     */
    private function groupPotentialDuplicates(Collection $orders): Collection
    {
        // Group by multiple criteria to identify potential duplicates
        $groups = collect();

        // Group by customer email + total amount + order date (within 1 hour)
        $emailGroups = $orders->filter(function ($order) {
            return !empty($order['customer_email']);
        })->groupBy(function ($order) {
            $orderDate = Carbon::parse($order['order_date']);
            $hourKey = $orderDate->format('Y-m-d-H');
            return $order['customer_email'] . '|' . $order['total_amount'] . '|' . $hourKey;
        });

        $groups = $groups->merge($emailGroups->filter(function ($group) {
            return $group->count() > 1;
        }));

        // Group by customer phone + total amount + order date (within 1 hour)
        $phoneGroups = $orders->filter(function ($order) {
            return !empty($order['customer_phone']);
        })->groupBy(function ($order) {
            $orderDate = Carbon::parse($order['order_date']);
            $hourKey = $orderDate->format('Y-m-d-H');
            $normalizedPhone = $this->normalizePhone($order['customer_phone']);
            return $normalizedPhone . '|' . $order['total_amount'] . '|' . $hourKey;
        });

        $groups = $groups->merge($phoneGroups->filter(function ($group) {
            return $group->count() > 1;
        }));

        // Group by customer name + shipping address + total amount (fuzzy matching)
        $addressGroups = $orders->filter(function ($order) {
            return !empty($order['customer_name']) && !empty($order['shipping_address']);
        })->groupBy(function ($order) {
            $normalizedName = $this->normalizeName($order['customer_name']);
            $normalizedAddress = $this->normalizeAddress($order['shipping_address']);
            return $normalizedName . '|' . $normalizedAddress . '|' . $order['total_amount'];
        });

        $groups = $groups->merge($addressGroups->filter(function ($group) {
            return $group->count() > 1;
        }));

        return $groups;
    }

    /**
     * Resolve a group of duplicate orders.
     */
    private function resolveDuplicateGroup(Collection $duplicateGroup): array
    {
        Log::info('Resolving duplicate group', [
            'orders' => $duplicateGroup->pluck('platform_order_id')->toArray(),
            'platforms' => $duplicateGroup->pluck('platform_type')->unique()->toArray()
        ]);

        // Check if orders are from different platforms (cross-platform duplicates)
        $platforms = $duplicateGroup->pluck('platform_type')->unique();
        
        if ($platforms->count() > 1) {
            return $this->resolveCrossPlatformDuplicates($duplicateGroup);
        } else {
            return $this->resolveSamePlatformDuplicates($duplicateGroup);
        }
    }

    /**
     * Resolve duplicates across different platforms.
     */
    private function resolveCrossPlatformDuplicates(Collection $duplicates): array
    {
        // Find the most authoritative order (usually the earliest one)
        $primaryOrder = $this->selectPrimaryOrder($duplicates);
        $secondaryOrders = $duplicates->reject(function ($order) use ($primaryOrder) {
            return $order['platform_order_id'] === $primaryOrder['platform_order_id'] &&
                   $order['platform_type'] === $primaryOrder['platform_type'];
        });

        $conflicts = [];
        $resolved = true;

        foreach ($secondaryOrders as $secondaryOrder) {
            // Check for conflicts between primary and secondary orders
            $conflictData = $this->conflictResolver->detectConflicts($primaryOrder, $secondaryOrder);
            
            if (!empty($conflictData)) {
                $conflicts[] = $conflictData;
                
                // Attempt to resolve conflicts
                $resolution = $this->conflictResolver->resolveConflicts($primaryOrder, $secondaryOrder, $conflictData);
                
                if (!$resolution['success']) {
                    $resolved = false;
                    Log::warning('Failed to resolve cross-platform conflict', [
                        'primary_order' => $primaryOrder['platform_order_id'],
                        'secondary_order' => $secondaryOrder['platform_order_id'],
                        'conflicts' => $conflictData
                    ]);
                }
            }
        }

        // Mark secondary orders as duplicates if resolved
        if ($resolved) {
            $this->markOrdersAsDuplicates($secondaryOrders, $primaryOrder);
        }

        return [
            'resolved' => $resolved,
            'conflicts' => !empty($conflicts),
            'primary_order' => $primaryOrder['platform_order_id'],
            'duplicate_count' => $secondaryOrders->count(),
            'conflict_details' => $conflicts
        ];
    }

    /**
     * Resolve duplicates within the same platform.
     */
    private function resolveSamePlatformDuplicates(Collection $duplicates): array
    {
        // For same-platform duplicates, keep the most recent one
        $primaryOrder = $duplicates->sortByDesc('order_date')->first();
        $duplicateOrders = $duplicates->reject(function ($order) use ($primaryOrder) {
            return $order['platform_order_id'] === $primaryOrder['platform_order_id'];
        });

        // Mark older orders as duplicates
        $this->markOrdersAsDuplicates($duplicateOrders, $primaryOrder);

        Log::info('Resolved same-platform duplicates', [
            'primary_order' => $primaryOrder['platform_order_id'],
            'duplicate_count' => $duplicateOrders->count()
        ]);

        return [
            'resolved' => true,
            'conflicts' => false,
            'primary_order' => $primaryOrder['platform_order_id'],
            'duplicate_count' => $duplicateOrders->count()
        ];
    }

    /**
     * Select the primary order from a group of duplicates.
     */
    private function selectPrimaryOrder(Collection $duplicates): array
    {
        // Priority order: Shopify > Lazada > Shopee > TikTok
        $platformPriority = ['shopify' => 4, 'lazada' => 3, 'shopee' => 2, 'tiktok' => 1];

        return $duplicates->sortBy([
            // First by platform priority (descending)
            function ($order) use ($platformPriority) {
                return -($platformPriority[$order['platform_type']] ?? 0);
            },
            // Then by order date (ascending - earliest first)
            function ($order) {
                return Carbon::parse($order['order_date'])->timestamp;
            }
        ])->first();
    }

    /**
     * Mark orders as duplicates in the database.
     */
    private function markOrdersAsDuplicates(Collection $duplicateOrders, array $primaryOrder): void
    {
        foreach ($duplicateOrders as $duplicateOrder) {
            try {
                DB::table('orders')
                    ->where('platform_order_id', $duplicateOrder['platform_order_id'])
                    ->where('platform_type', $duplicateOrder['platform_type'])
                    ->update([
                        'sync_status' => 'duplicate',
                        'notes' => 'Duplicate of order: ' . $primaryOrder['platform_order_id'] . ' (' . $primaryOrder['platform_type'] . ')',
                        'updated_at' => now()
                    ]);

                Log::debug('Marked order as duplicate', [
                    'duplicate_order' => $duplicateOrder['platform_order_id'],
                    'primary_order' => $primaryOrder['platform_order_id']
                ]);
            } catch (Exception $e) {
                Log::error('Failed to mark order as duplicate', [
                    'order_id' => $duplicateOrder['platform_order_id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Normalize phone number for comparison.
     */
    private function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters
        $normalized = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading country codes (common ones)
        $normalized = preg_replace('/^(1|60|65|66|84|62)/', '', $normalized);
        
        return $normalized;
    }

    /**
     * Normalize customer name for comparison.
     */
    private function normalizeName(string $name): string
    {
        // Convert to lowercase, remove extra spaces, and common punctuation
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        return $normalized;
    }

    /**
     * Normalize address for comparison.
     */
    private function normalizeAddress(string $address): string
    {
        // Convert to lowercase, remove extra spaces and common variations
        $normalized = strtolower(trim($address));
        
        // Replace common abbreviations
        $replacements = [
            'street' => 'st',
            'avenue' => 'ave',
            'boulevard' => 'blvd',
            'road' => 'rd',
            'drive' => 'dr',
            'apartment' => 'apt',
            'suite' => 'ste'
        ];
        
        foreach ($replacements as $full => $abbrev) {
            $normalized = str_replace($full, $abbrev, $normalized);
        }
        
        // Remove punctuation and normalize spaces
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        return $normalized;
    }

    /**
     * Generate a unique ID for a duplicate group.
     */
    private function generateGroupId(Collection $group): string
    {
        $orderIds = $group->pluck('platform_order_id')->sort()->implode('|');
        return md5($orderIds);
    }

    /**
     * Get duplicate statistics.
     */
    public function getDuplicateStatistics(): array
    {
        $totalOrders = Order::count();
        $duplicateOrders = Order::where('sync_status', 'duplicate')->count();
        
        $duplicatesByPlatform = Order::where('sync_status', 'duplicate')
            ->select('platform_type', DB::raw('count(*) as count'))
            ->groupBy('platform_type')
            ->pluck('count', 'platform_type')
            ->toArray();

        return [
            'total_orders' => $totalOrders,
            'duplicate_orders' => $duplicateOrders,
            'duplicate_percentage' => $totalOrders > 0 ? round(($duplicateOrders / $totalOrders) * 100, 2) : 0,
            'duplicates_by_platform' => $duplicatesByPlatform,
            'last_deduplication_run' => cache('last_deduplication_run')
        ];
    }

    /**
     * Clean up old duplicate markers.
     */
    public function cleanupOldDuplicates(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);
        
        $count = Order::where('sync_status', 'duplicate')
            ->where('updated_at', '<', $cutoffDate)
            ->update([
                'sync_status' => 'synced',
                'notes' => ''
            ]);

        Log::info('Cleaned up old duplicate markers', [
            'count' => $count,
            'cutoff_date' => $cutoffDate->toISOString()
        ]);

        return $count;
    }
}