<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;

class OrderNormalizer
{
    /**
     * Normalize order data from any platform to unified format.
     */
    public function normalize(array $rawOrderData, string $platformType): array
    {
        try {
            $normalizedData = match ($platformType) {
                'shopee' => $this->normalizeShopeeOrder($rawOrderData),
                'lazada' => $this->normalizeLazadaOrder($rawOrderData),
                'shopify' => $this->normalizeShopifyOrder($rawOrderData),
                'tiktok' => $this->normalizeTikTokOrder($rawOrderData),
                default => throw new Exception("Unsupported platform type: {$platformType}")
            };

            // Validate normalized data
            $this->validateNormalizedOrder($normalizedData);

            // Set default values
            $normalizedData = $this->setDefaultValues($normalizedData);

            Log::debug('Order normalized successfully', [
                'platform' => $platformType,
                'order_id' => $normalizedData['platform_order_id']
            ]);

            return $normalizedData;
        } catch (Exception $e) {
            Log::error('Order normalization failed', [
                'platform' => $platformType,
                'raw_data' => $rawOrderData,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Normalize Shopee order data.
     */
    private function normalizeShopeeOrder(array $orderData): array
    {
        return [
            'platform_order_id' => $orderData['order_sn'] ?? '',
            'platform_type' => 'shopee',
            'customer_name' => $orderData['recipient_address']['name'] ?? '',
            'customer_email' => '', // Shopee doesn't provide email in order data
            'customer_phone' => $orderData['recipient_address']['phone'] ?? '',
            'total_amount' => ($orderData['total_amount'] ?? 0) / 100000, // Shopee uses micro-currency
            'currency' => $orderData['currency'] ?? 'USD',
            'status' => $this->mapShopeeStatus($orderData['order_status'] ?? ''),
            'workflow_status' => 'new',
            'order_date' => isset($orderData['create_time']) 
                ? Carbon::createFromTimestamp($orderData['create_time']) 
                : now(),
            'sync_status' => 'synced',
            'shipping_address' => $this->normalizeShippingAddress($orderData['recipient_address'] ?? []),
            'billing_address' => $this->normalizeShippingAddress($orderData['recipient_address'] ?? []),
            'raw_data' => $orderData,
            'notes' => ''
        ];
    }

    /**
     * Normalize Lazada order data.
     */
    private function normalizeLazadaOrder(array $orderData): array
    {
        return [
            'platform_order_id' => $orderData['order_number'] ?? '',
            'platform_type' => 'lazada',
            'customer_name' => trim(($orderData['address_shipping']['first_name'] ?? '') . ' ' . ($orderData['address_shipping']['last_name'] ?? '')),
            'customer_email' => $orderData['customer_email'] ?? '',
            'customer_phone' => $orderData['address_shipping']['phone'] ?? '',
            'total_amount' => (float) ($orderData['price'] ?? 0),
            'currency' => $orderData['currency'] ?? 'USD',
            'status' => $this->mapLazadaStatus($orderData['statuses'] ?? []),
            'workflow_status' => 'new',
            'order_date' => isset($orderData['created_at']) 
                ? Carbon::parse($orderData['created_at']) 
                : now(),
            'sync_status' => 'synced',
            'shipping_address' => $this->normalizeLazadaAddress($orderData['address_shipping'] ?? []),
            'billing_address' => $this->normalizeLazadaAddress($orderData['address_billing'] ?? $orderData['address_shipping'] ?? []),
            'raw_data' => $orderData,
            'notes' => ''
        ];
    }

    /**
     * Normalize Shopify order data.
     */
    private function normalizeShopifyOrder(array $orderData): array
    {
        return [
            'platform_order_id' => $orderData['id'] ?? $orderData['order_number'] ?? '',
            'platform_type' => 'shopify',
            'customer_name' => $orderData['customer']['first_name'] . ' ' . $orderData['customer']['last_name'] ?? '',
            'customer_email' => $orderData['customer']['email'] ?? $orderData['email'] ?? '',
            'customer_phone' => $orderData['customer']['phone'] ?? $orderData['phone'] ?? '',
            'total_amount' => (float) ($orderData['total_price'] ?? 0),
            'currency' => $orderData['currency'] ?? 'USD',
            'status' => $this->mapShopifyStatus($orderData['financial_status'] ?? '', $orderData['fulfillment_status'] ?? ''),
            'workflow_status' => 'new',
            'order_date' => isset($orderData['created_at']) 
                ? Carbon::parse($orderData['created_at']) 
                : now(),
            'sync_status' => 'synced',
            'shipping_address' => $this->normalizeShopifyAddress($orderData['shipping_address'] ?? []),
            'billing_address' => $this->normalizeShopifyAddress($orderData['billing_address'] ?? []),
            'raw_data' => $orderData,
            'notes' => $orderData['note'] ?? ''
        ];
    }

    /**
     * Normalize TikTok order data.
     */
    private function normalizeTikTokOrder(array $orderData): array
    {
        return [
            'platform_order_id' => $orderData['order_id'] ?? '',
            'platform_type' => 'tiktok',
            'customer_name' => $orderData['recipient_info']['name'] ?? '',
            'customer_email' => $orderData['buyer_email'] ?? '',
            'customer_phone' => $orderData['recipient_info']['phone'] ?? '',
            'total_amount' => (float) ($orderData['payment_info']['total_amount'] ?? 0),
            'currency' => $orderData['payment_info']['currency'] ?? 'USD',
            'status' => $this->mapTikTokStatus($orderData['order_status'] ?? ''),
            'workflow_status' => 'new',
            'order_date' => isset($orderData['create_time']) 
                ? Carbon::createFromTimestamp($orderData['create_time']) 
                : now(),
            'sync_status' => 'synced',
            'shipping_address' => $this->normalizeTikTokAddress($orderData['recipient_info'] ?? []),
            'billing_address' => $this->normalizeTikTokAddress($orderData['recipient_info'] ?? []),
            'raw_data' => $orderData,
            'notes' => $orderData['buyer_message'] ?? ''
        ];
    }

    /**
     * Normalize shipping address for Shopee/general format.
     */
    private function normalizeShippingAddress(array $address): string
    {
        $parts = array_filter([
            $address['full_address'] ?? $address['address'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['country'] ?? '',
            $address['zipcode'] ?? $address['postal_code'] ?? ''
        ]);

        return implode(', ', $parts);
    }

    /**
     * Normalize Lazada address format.
     */
    private function normalizeLazadaAddress(array $address): string
    {
        $parts = array_filter([
            $address['address1'] ?? '',
            $address['address2'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['country'] ?? '',
            $address['post_code'] ?? ''
        ]);

        return implode(', ', $parts);
    }

    /**
     * Normalize Shopify address format.
     */
    private function normalizeShopifyAddress(array $address): string
    {
        $parts = array_filter([
            $address['address1'] ?? '',
            $address['address2'] ?? '',
            $address['city'] ?? '',
            $address['province'] ?? '',
            $address['country'] ?? '',
            $address['zip'] ?? ''
        ]);

        return implode(', ', $parts);
    }

    /**
     * Normalize TikTok address format.
     */
    private function normalizeTikTokAddress(array $address): string
    {
        $parts = array_filter([
            $address['address_line1'] ?? '',
            $address['address_line2'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['country'] ?? '',
            $address['postal_code'] ?? ''
        ]);

        return implode(', ', $parts);
    }

    /**
     * Map Shopee status to internal status.
     */
    private function mapShopeeStatus(string $shopeeStatus): string
    {
        return match ($shopeeStatus) {
            'UNPAID' => 'pending',
            'READY_TO_SHIP' => 'confirmed',
            'SHIPPED' => 'shipped',
            'DELIVERED' => 'delivered',
            'COMPLETED' => 'delivered',
            'CANCELLED' => 'cancelled',
            'INVOICE_PENDING' => 'pending',
            default => 'pending'
        };
    }

    /**
     * Map Lazada status to internal status.
     */
    private function mapLazadaStatus(array $statuses): string
    {
        $latestStatus = end($statuses) ?: 'pending';
        
        return match ($latestStatus) {
            'pending' => 'pending',
            'ready_to_ship' => 'confirmed',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'canceled' => 'cancelled',
            'returned' => 'refunded',
            default => 'pending'
        };
    }

    /**
     * Map Shopify status to internal status.
     */
    private function mapShopifyStatus(string $financialStatus, string $fulfillmentStatus): string
    {
        // Priority: fulfillment status over financial status
        if ($fulfillmentStatus) {
            return match ($fulfillmentStatus) {
                'fulfilled' => 'delivered',
                'partial' => 'processing',
                'unfulfilled' => 'confirmed',
                default => 'pending'
            };
        }

        return match ($financialStatus) {
            'paid' => 'confirmed',
            'pending' => 'pending',
            'refunded' => 'refunded',
            'voided' => 'cancelled',
            default => 'pending'
        };
    }

    /**
     * Map TikTok status to internal status.
     */
    private function mapTikTokStatus(string $tiktokStatus): string
    {
        return match ($tiktokStatus) {
            'AWAITING_SHIPMENT' => 'confirmed',
            'AWAITING_COLLECTION' => 'processing',
            'IN_TRANSIT' => 'shipped',
            'DELIVERED' => 'delivered',
            'COMPLETED' => 'delivered',
            'CANCELLED' => 'cancelled',
            default => 'pending'
        };
    }

    /**
     * Validate normalized order data.
     */
    private function validateNormalizedOrder(array $orderData): void
    {
        $validator = Validator::make($orderData, [
            'platform_order_id' => 'required|string',
            'platform_type' => 'required|in:shopee,lazada,shopify,tiktok',
            'customer_name' => 'required|string',
            'total_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'status' => 'required|string',
            'order_date' => 'required|date'
        ]);

        if ($validator->fails()) {
            throw new Exception('Order validation failed: ' . implode(', ', $validator->errors()->all()));
        }
    }

    /**
     * Set default values for normalized order data.
     */
    private function setDefaultValues(array $orderData): array
    {
        return array_merge([
            'customer_email' => '',
            'customer_phone' => '',
            'workflow_status' => 'new',
            'sync_status' => 'synced',
            'shipping_address' => '',
            'billing_address' => '',
            'notes' => ''
        ], $orderData);
    }

    /**
     * Batch normalize multiple orders.
     */
    public function batchNormalize(array $rawOrders, string $platformType): array
    {
        $normalizedOrders = [];
        $errors = [];

        foreach ($rawOrders as $index => $rawOrder) {
            try {
                $normalizedOrders[] = $this->normalize($rawOrder, $platformType);
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'order_id' => $rawOrder['platform_order_id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        if (!empty($errors)) {
            Log::warning('Batch normalization completed with errors', [
                'platform' => $platformType,
                'total_orders' => count($rawOrders),
                'successful' => count($normalizedOrders),
                'errors' => count($errors),
                'error_details' => $errors
            ]);
        }

        return $normalizedOrders;
    }
}