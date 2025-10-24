<?php

namespace App\Services\PlatformConnectors;

use App\Services\PlatformCredentialManager;
use App\Services\CircuitBreakerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Carbon\Carbon;
use Exception;

class ShopeeConnector extends AbstractPlatformConnector
{
    private CircuitBreakerService $circuitBreaker;

    public function __construct(PlatformCredentialManager $credentialManager)
    {
        parent::__construct($credentialManager);
        $this->circuitBreaker = app(CircuitBreakerService::class, ['serviceName' => 'shopee']);
    }

    /**
     * Initialize Shopee-specific configuration.
     */
    protected function initializePlatformConfig(): void
    {
        $this->platformType = 'shopee';
        $this->rateLimits = config('platforms.rate_limits.shopee', [
            'requests_per_minute' => 100,
            'burst_limit' => 10
        ]);
    }

    /**
     * Get the base API URL for Shopee.
     */
    protected function getBaseUrl(): string
    {
        $endpoints = config('platforms.endpoints.shopee');
        return $this->getEnvironmentEndpoint(
            $endpoints['sandbox'],
            $endpoints['production']
        );
    }

    /**
     * Get Shopee-specific headers for API requests.
     */
    protected function getHeaders(): array
    {
        if (!$this->credentials) {
            $this->loadCredentials();
        }

        $timestamp = time();
        $partnerId = $this->credentials['partner_id'];
        $partnerKey = $this->credentials['partner_key'];

        return [
            'Content-Type' => 'application/json',
            'Authorization' => $this->generateShopeeAuth($partnerId, $partnerKey, $timestamp),
            'timestamp' => $timestamp,
            'partner-id' => $partnerId,
        ];
    }

    /**
     * Generate Shopee OAuth 2.0 authorization header.
     */
    private function generateShopeeAuth(string $partnerId, string $partnerKey, int $timestamp): string
    {
        // Shopee uses HMAC-SHA256 for request signing
        $baseString = sprintf('%s%s%s', $partnerId, '/api/v2/auth/token/get', $timestamp);
        $signature = hash_hmac('sha256', $baseString, $partnerKey);
        
        return "SHA256 {$signature}";
    }

    /**
     * Get allowed domains for Shopee.
     */
    protected function getAllowedDomains(): array
    {
        return config('platforms.endpoints.shopee.allowed_domains', [
            'partner.shopeemobile.com',
            'partner.test-stable.shopeemobile.com',
        ]);
    }

    /**
     * Test connection to Shopee API.
     */
    public function testConnection(): bool
    {
        try {
            if (!$this->loadCredentials()) {
                Log::warning('No credentials available for Shopee connection test');
                return false;
            }

            return $this->circuitBreaker->call(function () {
                $response = $this->makeRequest('POST', '/api/v2/auth/token/get', [
                    'code' => 'test_connection',
                    'shop_id' => $this->credentials['shop_id'] ?? 0,
                    'partner_id' => $this->credentials['partner_id']
                ]);

                // Shopee returns 200 even for auth errors, check response body
                $data = $response->json();
                
                if (isset($data['error']) && $data['error'] !== '') {
                    Log::warning('Shopee connection test failed', [
                        'error' => $data['error'],
                        'message' => $data['message'] ?? 'Unknown error'
                    ]);
                    return false;
                }

                return $response->successful();
            });
        } catch (Exception $e) {
            Log::error('Shopee connection test exception', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Fetch orders from Shopee.
     */
    public function fetchOrders(Carbon $since = null): Collection
    {
        try {
            return $this->circuitBreaker->call(function () use ($since) {
                $orders = collect();
                $pageSize = 100;
                $cursor = '';
                
                do {
                    $params = [
                        'page_size' => $pageSize,
                        'cursor' => $cursor,
                        'order_status' => 'READY_TO_SHIP,SHIPPED,DELIVERED,COMPLETED',
                        'response_optional_fields' => 'order_status,total_amount,create_time,update_time'
                    ];

                    if ($since) {
                        $params['time_from'] = $since->timestamp;
                        $params['time_to'] = now()->timestamp;
                        $params['time_range_field'] = 'create_time';
                    }

                    $response = $this->makeRequest('GET', '/api/v2/order/get_order_list', $params);
                    
                    if (!$response->successful()) {
                        throw new Exception("Shopee API error: " . $response->body());
                    }

                    $data = $response->json();
                    
                    if (isset($data['error']) && $data['error'] !== '') {
                        throw new Exception("Shopee API error: " . $data['message']);
                    }

                    $orderList = $data['response']['order_list'] ?? [];
                    
                    foreach ($orderList as $orderData) {
                        $orders->push($this->normalizeOrderData($orderData));
                    }

                    $cursor = $data['response']['next_cursor'] ?? '';
                    
                } while (!empty($cursor) && $orders->count() < 1000); // Safety limit

                Log::info('Shopee orders fetched', [
                    'count' => $orders->count(),
                    'since' => $since?->toISOString()
                ]);

                return $orders;
            });
        } catch (Exception $e) {
            Log::error('Failed to fetch Shopee orders', [
                'error' => $e->getMessage(),
                'since' => $since?->toISOString()
            ]);
            return collect();
        }
    }

    /**
     * Update order status on Shopee.
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        try {
            return $this->circuitBreaker->call(function () use ($orderId, $status) {
                $shopeeStatus = $this->mapStatusToShopee($status);
                
                if (!$shopeeStatus) {
                    Log::warning('Cannot map status to Shopee', [
                        'order_id' => $orderId,
                        'status' => $status
                    ]);
                    return false;
                }

                $response = $this->makeRequest('POST', '/api/v2/order/ship_order', [
                    'order_sn' => $orderId,
                    'package_number' => '',
                    'pickup' => [
                        'address_id' => $this->credentials['pickup_address_id'] ?? null,
                        'pickup_time_id' => $this->credentials['pickup_time_id'] ?? null,
                    ]
                ]);

                if (!$response->successful()) {
                    Log::error('Failed to update Shopee order status', [
                        'order_id' => $orderId,
                        'status' => $status,
                        'response' => $response->body()
                    ]);
                    return false;
                }

                $data = $response->json();
                
                if (isset($data['error']) && $data['error'] !== '') {
                    Log::error('Shopee order status update error', [
                        'order_id' => $orderId,
                        'error' => $data['message']
                    ]);
                    return false;
                }

                return true;
            });
        } catch (Exception $e) {
            Log::error('Exception updating Shopee order status', [
                'order_id' => $orderId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Transform Shopee order data to normalized format.
     */
    protected function normalizeOrderData(array $orderData): array
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
            'order_date' => isset($orderData['create_time']) 
                ? Carbon::createFromTimestamp($orderData['create_time']) 
                : now(),
            'items' => $this->normalizeOrderItems($orderData['item_list'] ?? []),
            'shipping_address' => $this->normalizeShippingAddress($orderData['recipient_address'] ?? []),
            'raw_data' => $orderData,
        ];
    }

    /**
     * Normalize order items.
     */
    private function normalizeOrderItems(array $items): array
    {
        return array_map(function ($item) {
            return [
                'sku' => $item['item_sku'] ?? '',
                'name' => $item['item_name'] ?? '',
                'quantity' => $item['model_quantity_purchased'] ?? 1,
                'price' => ($item['model_original_price'] ?? 0) / 100000,
                'variation' => $item['model_name'] ?? '',
            ];
        }, $items);
    }

    /**
     * Normalize shipping address.
     */
    private function normalizeShippingAddress(array $address): array
    {
        return [
            'name' => $address['name'] ?? '',
            'phone' => $address['phone'] ?? '',
            'address_line_1' => $address['full_address'] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'country' => $address['country'] ?? '',
            'postal_code' => $address['zipcode'] ?? '',
        ];
    }

    /**
     * Map internal status to Shopee status.
     */
    private function mapStatusToShopee(string $status): ?string
    {
        return match ($status) {
            'processing' => 'READY_TO_SHIP',
            'shipped' => 'SHIPPED',
            'delivered' => 'DELIVERED',
            'completed' => 'COMPLETED',
            'cancelled' => 'CANCELLED',
            default => null
        };
    }

    /**
     * Map Shopee status to internal status.
     */
    private function mapShopeeStatus(string $shopeeStatus): string
    {
        return match ($shopeeStatus) {
            'UNPAID' => 'pending_payment',
            'READY_TO_SHIP' => 'processing',
            'SHIPPED' => 'shipped',
            'DELIVERED' => 'delivered',
            'COMPLETED' => 'completed',
            'CANCELLED' => 'cancelled',
            'INVOICE_PENDING' => 'pending_invoice',
            default => 'unknown'
        };
    }

    /**
     * Get platform-specific configuration schema.
     */
    public function getConfigurationSchema(): array
    {
        return [
            'partner_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Shopee Partner ID',
                'validation' => 'numeric'
            ],
            'partner_key' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Shopee Partner Key',
                'validation' => 'min:32'
            ],
            'shop_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Shopee Shop ID',
                'validation' => 'numeric'
            ],
            'pickup_address_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Default pickup address ID for shipping'
            ],
            'pickup_time_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Default pickup time ID for shipping'
            ]
        ];
    }

    /**
     * Verify Shopee webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        // Shopee uses HMAC-SHA256 for webhook signature verification
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        // Shopee sends signature in hex format
        return hash_equals($expectedSignature, $signature);
    }
}