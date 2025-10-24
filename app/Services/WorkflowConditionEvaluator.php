<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class WorkflowConditionEvaluator
{
    /**
     * Evaluate complex conditions with AND/OR logic.
     */
    public function evaluate(Order $order, array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        // Handle grouped conditions with logical operators
        if (isset($conditions['operator'])) {
            return $this->evaluateGroupedConditions($order, $conditions);
        }

        // Handle simple array of conditions (default AND logic)
        foreach ($conditions as $condition) {
            if (!$this->evaluateSingleCondition($order, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate grouped conditions with AND/OR operators.
     */
    private function evaluateGroupedConditions(Order $order, array $conditionGroup): bool
    {
        $operator = strtoupper($conditionGroup['operator'] ?? 'AND');
        $conditions = $conditionGroup['conditions'] ?? [];

        if ($operator === 'AND') {
            foreach ($conditions as $condition) {
                if (!$this->evaluateCondition($order, $condition)) {
                    return false;
                }
            }
            return true;
        }

        if ($operator === 'OR') {
            foreach ($conditions as $condition) {
                if ($this->evaluateCondition($order, $condition)) {
                    return true;
                }
            }
            return false;
        }

        Log::warning("Unknown condition operator: {$operator}");
        return false;
    }

    /**
     * Evaluate a single condition or nested group.
     */
    private function evaluateCondition(Order $order, array $condition): bool
    {
        // Handle nested condition groups
        if (isset($condition['operator']) && isset($condition['conditions'])) {
            return $this->evaluateGroupedConditions($order, $condition);
        }

        // Handle single condition
        return $this->evaluateSingleCondition($order, $condition);
    }    
/**
     * Evaluate a single condition.
     */
    private function evaluateSingleCondition(Order $order, array $condition): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? '';

        // Get the value from the order using dot notation
        $orderValue = $this->getOrderValue($order, $field);

        return $this->compareValues($orderValue, $operator, $value);
    }

    /**
     * Get value from order using dot notation.
     */
    private function getOrderValue(Order $order, string $field): mixed
    {
        // Handle special fields
        if ($field === 'order_items_count') {
            return $order->orderItems()->count();
        }

        if ($field === 'order_items_total_quantity') {
            return $order->orderItems()->sum('quantity');
        }

        if (str_starts_with($field, 'order_items.')) {
            // Handle order items aggregations
            $subField = str_replace('order_items.', '', $field);
            return $order->orderItems()->pluck($subField)->toArray();
        }

        if (str_starts_with($field, 'raw_data.')) {
            // Handle raw data fields
            $rawDataField = str_replace('raw_data.', '', $field);
            return data_get($order->raw_data, $rawDataField);
        }

        // Handle standard model attributes
        return data_get($order, $field);
    }

    /**
     * Compare values using the specified operator.
     */
    private function compareValues(mixed $orderValue, string $operator, mixed $expectedValue): bool
    {
        return match ($operator) {
            '=' => $orderValue == $expectedValue,
            '!=' => $orderValue != $expectedValue,
            '>' => $this->numericCompare($orderValue, $expectedValue, '>'),
            '<' => $this->numericCompare($orderValue, $expectedValue, '<'),
            '>=' => $this->numericCompare($orderValue, $expectedValue, '>='),
            '<=' => $this->numericCompare($orderValue, $expectedValue, '<='),
            'in' => in_array($orderValue, (array) $expectedValue),
            'not_in' => !in_array($orderValue, (array) $expectedValue),
            'contains' => $this->stringContains($orderValue, $expectedValue),
            'not_contains' => !$this->stringContains($orderValue, $expectedValue),
            'starts_with' => str_starts_with((string) $orderValue, (string) $expectedValue),
            'ends_with' => str_ends_with((string) $orderValue, (string) $expectedValue),
            'regex' => preg_match('/' . $expectedValue . '/', (string) $orderValue),
            'is_null' => is_null($orderValue),
            'is_not_null' => !is_null($orderValue),
            'is_empty' => empty($orderValue),
            'is_not_empty' => !empty($orderValue),
            'between' => $this->isBetween($orderValue, $expectedValue),
            'not_between' => !$this->isBetween($orderValue, $expectedValue),
            default => false
        };
    }

    /**
     * Perform numeric comparison.
     */
    private function numericCompare(mixed $orderValue, mixed $expectedValue, string $operator): bool
    {
        if (!is_numeric($orderValue) || !is_numeric($expectedValue)) {
            return false;
        }

        return match ($operator) {
            '>' => (float) $orderValue > (float) $expectedValue,
            '<' => (float) $orderValue < (float) $expectedValue,
            '>=' => (float) $orderValue >= (float) $expectedValue,
            '<=' => (float) $orderValue <= (float) $expectedValue,
            default => false
        };
    }    /**

     * Check if string contains substring (case-insensitive).
     */
    private function stringContains(mixed $haystack, mixed $needle): bool
    {
        return str_contains(strtolower((string) $haystack), strtolower((string) $needle));
    }

    /**
     * Check if value is between two values.
     */
    private function isBetween(mixed $value, mixed $range): bool
    {
        if (!is_array($range) || count($range) !== 2) {
            return false;
        }

        [$min, $max] = $range;
        
        if (!is_numeric($value) || !is_numeric($min) || !is_numeric($max)) {
            return false;
        }

        return (float) $value >= (float) $min && (float) $value <= (float) $max;
    }

    /**
     * Get available condition operators.
     */
    public static function getAvailableOperators(): array
    {
        return [
            '=' => 'Equals',
            '!=' => 'Not equals',
            '>' => 'Greater than',
            '<' => 'Less than',
            '>=' => 'Greater than or equal',
            '<=' => 'Less than or equal',
            'in' => 'In list',
            'not_in' => 'Not in list',
            'contains' => 'Contains',
            'not_contains' => 'Does not contain',
            'starts_with' => 'Starts with',
            'ends_with' => 'Ends with',
            'regex' => 'Matches regex',
            'is_null' => 'Is null',
            'is_not_null' => 'Is not null',
            'is_empty' => 'Is empty',
            'is_not_empty' => 'Is not empty',
            'between' => 'Between',
            'not_between' => 'Not between',
        ];
    }

    /**
     * Get available order fields for conditions.
     */
    public static function getAvailableFields(): array
    {
        return [
            'platform_type' => 'Platform Type',
            'customer_name' => 'Customer Name',
            'customer_email' => 'Customer Email',
            'total_amount' => 'Total Amount',
            'currency' => 'Currency',
            'status' => 'Order Status',
            'workflow_status' => 'Workflow Status',
            'order_date' => 'Order Date',
            'sync_status' => 'Sync Status',
            'order_items_count' => 'Number of Items',
            'order_items_total_quantity' => 'Total Quantity',
            'raw_data.payment_method' => 'Payment Method (Raw Data)',
            'raw_data.shipping_method' => 'Shipping Method (Raw Data)',
            'raw_data.priority' => 'Priority (Raw Data)',
        ];
    }
}