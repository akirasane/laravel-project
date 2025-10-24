<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class OrderConflictResolver
{
    /**
     * Detect conflicts between two orders.
     */
    public function detectConflicts(array $primaryOrder, array $secondaryOrder): array
    {
        $conflicts = [];

        // Check for status conflicts
        if ($this->hasStatusConflict($primaryOrder, $secondaryOrder)) {
            $conflicts['status'] = [
                'primary' => $primaryOrder['status'],
                'secondary' => $secondaryOrder['status'],
                'severity' => $this->getStatusConflictSeverity($primaryOrder['status'], $secondaryOrder['status'])
            ];
        }

        // Check for amount conflicts
        if ($this->hasAmountConflict($primaryOrder, $secondaryOrder)) {
            $conflicts['amount'] = [
                'primary' => $primaryOrder['total_amount'],
                'secondary' => $secondaryOrder['total_amount'],
                'difference' => abs($primaryOrder['total_amount'] - $secondaryOrder['total_amount']),
                'severity' => $this->getAmountConflictSeverity($primaryOrder['total_amount'], $secondaryOrder['total_amount'])
            ];
        }

        // Check for customer information conflicts
        $customerConflicts = $this->detectCustomerConflicts($primaryOrder, $secondaryOrder);
        if (!empty($customerConflicts)) {
            $conflicts['customer'] = $customerConflicts;
        }

        // Check for date conflicts
        if ($this->hasDateConflict($primaryOrder, $secondaryOrder)) {
            $conflicts['date'] = [
                'primary' => $primaryOrder['order_date'],
                'secondary' => $secondaryOrder['order_date'],
                'difference_hours' => $this->getDateDifferenceHours($primaryOrder['order_date'], $secondaryOrder['order_date']),
                'severity' => 'low'
            ];
        }

        return $conflicts;
    }

    /**
     * Resolve conflicts between two orders.
     */
    public function resolveConflicts(array $primaryOrder, array $secondaryOrder, array $conflicts): array
    {
        $resolutions = [];
        $success = true;

        foreach ($conflicts as $conflictType => $conflictData) {
            try {
                $resolution = match ($conflictType) {
                    'status' => $this->resolveStatusConflict($primaryOrder, $secondaryOrder, $conflictData),
                    'amount' => $this->resolveAmountConflict($primaryOrder, $secondaryOrder, $conflictData),
                    'customer' => $this->resolveCustomerConflicts($primaryOrder, $secondaryOrder, $conflictData),
                    'date' => $this->resolveDateConflict($primaryOrder, $secondaryOrder, $conflictData),
                    default => ['action' => 'manual_review', 'success' => false]
                };

                $resolutions[$conflictType] = $resolution;
                
                if (!$resolution['success']) {
                    $success = false;
                }
            } catch (Exception $e) {
                $resolutions[$conflictType] = [
                    'action' => 'error',
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $success = false;
            }
        }

        Log::info('Conflict resolution completed', [
            'primary_order' => $primaryOrder['platform_order_id'],
            'secondary_order' => $secondaryOrder['platform_order_id'],
            'success' => $success,
            'resolutions' => $resolutions
        ]);

        return [
            'success' => $success,
            'resolutions' => $resolutions
        ];
    }

    /**
     * Check if there's a status conflict.
     */
    private function hasStatusConflict(array $primaryOrder, array $secondaryOrder): bool
    {
        $primaryStatus = $this->normalizeStatus($primaryOrder['status']);
        $secondaryStatus = $this->normalizeStatus($secondaryOrder['status']);

        // No conflict if statuses are equivalent
        if ($primaryStatus === $secondaryStatus) {
            return false;
        }

        // Check if one status is a progression of the other
        return !$this->isStatusProgression($primaryStatus, $secondaryStatus);
    }

    /**
     * Check if there's an amount conflict.
     */
    private function hasAmountConflict(array $primaryOrder, array $secondaryOrder): bool
    {
        $difference = abs($primaryOrder['total_amount'] - $secondaryOrder['total_amount']);
        $threshold = max($primaryOrder['total_amount'], $secondaryOrder['total_amount']) * 0.05; // 5% threshold
        
        return $difference > $threshold && $difference > 1.00; // At least $1 difference
    }

    /**
     * Check if there's a date conflict.
     */
    private function hasDateConflict(array $primaryOrder, array $secondaryOrder): bool
    {
        $hoursDifference = $this->getDateDifferenceHours($primaryOrder['order_date'], $secondaryOrder['order_date']);
        return $hoursDifference > 24; // More than 24 hours apart
    }

    /**
     * Detect customer information conflicts.
     */
    private function detectCustomerConflicts(array $primaryOrder, array $secondaryOrder): array
    {
        $conflicts = [];

        // Name conflict
        if (!empty($primaryOrder['customer_name']) && !empty($secondaryOrder['customer_name'])) {
            $similarity = $this->calculateNameSimilarity($primaryOrder['customer_name'], $secondaryOrder['customer_name']);
            if ($similarity < 0.8) { // Less than 80% similar
                $conflicts['name'] = [
                    'primary' => $primaryOrder['customer_name'],
                    'secondary' => $secondaryOrder['customer_name'],
                    'similarity' => $similarity,
                    'severity' => 'medium'
                ];
            }
        }

        // Email conflict
        if (!empty($primaryOrder['customer_email']) && !empty($secondaryOrder['customer_email'])) {
            if (strtolower($primaryOrder['customer_email']) !== strtolower($secondaryOrder['customer_email'])) {
                $conflicts['email'] = [
                    'primary' => $primaryOrder['customer_email'],
                    'secondary' => $secondaryOrder['customer_email'],
                    'severity' => 'high'
                ];
            }
        }

        // Phone conflict
        if (!empty($primaryOrder['customer_phone']) && !empty($secondaryOrder['customer_phone'])) {
            $primaryPhone = $this->normalizePhone($primaryOrder['customer_phone']);
            $secondaryPhone = $this->normalizePhone($secondaryOrder['customer_phone']);
            
            if ($primaryPhone !== $secondaryPhone) {
                $conflicts['phone'] = [
                    'primary' => $primaryOrder['customer_phone'],
                    'secondary' => $secondaryOrder['customer_phone'],
                    'severity' => 'medium'
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Resolve status conflicts.
     */
    private function resolveStatusConflict(array $primaryOrder, array $secondaryOrder, array $conflictData): array
    {
        $primaryStatus = $this->normalizeStatus($primaryOrder['status']);
        $secondaryStatus = $this->normalizeStatus($secondaryOrder['status']);

        // Use the more advanced status
        $statusHierarchy = [
            'pending' => 1,
            'confirmed' => 2,
            'processing' => 3,
            'shipped' => 4,
            'delivered' => 5,
            'cancelled' => 6,
            'refunded' => 7
        ];

        $primaryLevel = $statusHierarchy[$primaryStatus] ?? 0;
        $secondaryLevel = $statusHierarchy[$secondaryStatus] ?? 0;

        if ($conflictData['severity'] === 'high') {
            // High severity conflicts need manual review
            return [
                'action' => 'manual_review',
                'success' => false,
                'reason' => 'High severity status conflict requires manual intervention'
            ];
        }

        // Use the more advanced status
        $resolvedStatus = $primaryLevel >= $secondaryLevel ? $primaryStatus : $secondaryStatus;

        return [
            'action' => 'use_advanced_status',
            'success' => true,
            'resolved_status' => $resolvedStatus,
            'reason' => 'Used more advanced status in order lifecycle'
        ];
    }

    /**
     * Resolve amount conflicts.
     */
    private function resolveAmountConflict(array $primaryOrder, array $secondaryOrder, array $conflictData): array
    {
        if ($conflictData['severity'] === 'high') {
            return [
                'action' => 'manual_review',
                'success' => false,
                'reason' => 'Significant amount difference requires manual review'
            ];
        }

        // For medium/low severity, use the higher amount (assuming it includes taxes/fees)
        $resolvedAmount = max($primaryOrder['total_amount'], $secondaryOrder['total_amount']);

        return [
            'action' => 'use_higher_amount',
            'success' => true,
            'resolved_amount' => $resolvedAmount,
            'reason' => 'Used higher amount assuming it includes additional fees'
        ];
    }

    /**
     * Resolve customer information conflicts.
     */
    private function resolveCustomerConflicts(array $primaryOrder, array $secondaryOrder, array $conflicts): array
    {
        $resolutions = [];
        $overallSuccess = true;

        foreach ($conflicts as $field => $conflictData) {
            if ($conflictData['severity'] === 'high') {
                $resolutions[$field] = [
                    'action' => 'manual_review',
                    'success' => false,
                    'reason' => 'High severity customer data conflict'
                ];
                $overallSuccess = false;
            } else {
                // For medium/low severity, prefer non-empty values from primary order
                $primaryValue = $primaryOrder["customer_{$field}"] ?? '';
                $secondaryValue = $secondaryOrder["customer_{$field}"] ?? '';
                
                $resolvedValue = !empty($primaryValue) ? $primaryValue : $secondaryValue;
                
                $resolutions[$field] = [
                    'action' => 'use_primary_or_fallback',
                    'success' => true,
                    'resolved_value' => $resolvedValue,
                    'reason' => 'Used primary order value or fallback to secondary'
                ];
            }
        }

        return [
            'action' => 'resolve_individual_fields',
            'success' => $overallSuccess,
            'field_resolutions' => $resolutions
        ];
    }

    /**
     * Resolve date conflicts.
     */
    private function resolveDateConflict(array $primaryOrder, array $secondaryOrder, array $conflictData): array
    {
        // Use the earlier date (first order placed)
        $primaryDate = Carbon::parse($primaryOrder['order_date']);
        $secondaryDate = Carbon::parse($secondaryOrder['order_date']);
        
        $resolvedDate = $primaryDate->lt($secondaryDate) ? $primaryOrder['order_date'] : $secondaryOrder['order_date'];

        return [
            'action' => 'use_earlier_date',
            'success' => true,
            'resolved_date' => $resolvedDate,
            'reason' => 'Used earlier order date as the original order time'
        ];
    }

    /**
     * Normalize status for comparison.
     */
    private function normalizeStatus(string $status): string
    {
        $statusMap = [
            'pending_payment' => 'pending',
            'awaiting_payment' => 'pending',
            'paid' => 'confirmed',
            'ready_to_ship' => 'processing',
            'in_transit' => 'shipped',
            'completed' => 'delivered',
            'canceled' => 'cancelled',
            'returned' => 'refunded'
        ];

        return $statusMap[$status] ?? $status;
    }

    /**
     * Check if one status is a natural progression of another.
     */
    private function isStatusProgression(string $status1, string $status2): bool
    {
        $progressions = [
            'pending' => ['confirmed', 'processing', 'shipped', 'delivered'],
            'confirmed' => ['processing', 'shipped', 'delivered'],
            'processing' => ['shipped', 'delivered'],
            'shipped' => ['delivered']
        ];

        return in_array($status2, $progressions[$status1] ?? []) || 
               in_array($status1, $progressions[$status2] ?? []);
    }

    /**
     * Get status conflict severity.
     */
    private function getStatusConflictSeverity(string $status1, string $status2): string
    {
        $conflictingStatuses = [
            ['cancelled', 'delivered'],
            ['refunded', 'delivered'],
            ['cancelled', 'shipped']
        ];

        foreach ($conflictingStatuses as $conflict) {
            if (in_array($status1, $conflict) && in_array($status2, $conflict)) {
                return 'high';
            }
        }

        return 'medium';
    }

    /**
     * Get amount conflict severity.
     */
    private function getAmountConflictSeverity(float $amount1, float $amount2): string
    {
        $difference = abs($amount1 - $amount2);
        $maxAmount = max($amount1, $amount2);
        $percentageDiff = $maxAmount > 0 ? ($difference / $maxAmount) * 100 : 0;

        if ($percentageDiff > 20 || $difference > 100) {
            return 'high';
        } elseif ($percentageDiff > 10 || $difference > 50) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Calculate name similarity.
     */
    private function calculateNameSimilarity(string $name1, string $name2): float
    {
        $name1 = strtolower(trim($name1));
        $name2 = strtolower(trim($name2));

        if ($name1 === $name2) {
            return 1.0;
        }

        // Use Levenshtein distance for similarity
        $maxLength = max(strlen($name1), strlen($name2));
        if ($maxLength === 0) {
            return 1.0;
        }

        $distance = levenshtein($name1, $name2);
        return 1 - ($distance / $maxLength);
    }

    /**
     * Normalize phone number.
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Get date difference in hours.
     */
    private function getDateDifferenceHours(string $date1, string $date2): float
    {
        $carbon1 = Carbon::parse($date1);
        $carbon2 = Carbon::parse($date2);
        
        return abs($carbon1->diffInHours($carbon2));
    }
}