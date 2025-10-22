<?php

namespace App\Services\PlatformConnectors;

use App\Services\PlatformCredentialManager;
use App\Services\CircuitBreakerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Carbon\Carbon;
use Exception;

class TikTokConnector extends AbstractPlatformConnector
{
    private CircuitBreakerService $circuitBreaker;

    public function __construct(PlatformCredentialManager $credentialManager)
    {
        parent::__construct($credentialManager);
        $this->circuitBreaker = app(CircuitBreakerService::class, ['serviceName' => 'tiktok']);
    }

    /**
     * Initialize TikTok-specific configuration.
     */
    protected function initializePlatformConfig(): void
    {
        $this->platformType = 'tiktok';
        $this->rateLimits = config('platforms.rate_limits.tiktok', [
            'requests_per_minute' => 120,
            'burst_limit' => 12
        ]);
    }

    /**
     * Get the base API URL for TikTok.
     */
    protected function getBaseUrl(): string
    {
        $endpoints = config('platforms.endpoints.tiktok');
        return $this->getEnvironmentEndpoint(
            $endpoints['sandbox'],
            $endpoints['production']
        );
    }

    /**
     * Get TikTok-specific headers for API requests.
     */
    protected function getHeaders(): array
    {
        if (!$this->credentials) {
            $this->loadCredentials();
        }

        $timestamp = time();
        $signature = $this->generateTikTokSignature($timestamp);

        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->credentials['access_token'],
            'x-tts-access-token' => $this->credentials['access_token'],
            'x-tts-timestamp' => $timestamp,
            'x-tts-signature' => $signature,
        ];
    }

    /**
     * Generate TikTok API signature.
     */
    private function generateTikTokSignature(int $timestamp): string
    {
        $appKey = $this->credentials['app_key'];
        $appSecret = $this->credentials['app_secret'];
        $accessToken = $this->credentials['access_token'];
        
        // TikTok signature format: HMAC-SHA256(app_key + timestamp + access_token, app_secret)
        $signString = $appKey . $timestamp . $accessToken;
        
        return hash_hmac('sha256', $signString, $appSecret);
    }

    /**
     * Get allowed domains for TikTok.
     */
    protected function getAllowedDomains(): array
    {
        return config('platforms.endpoints.tiktok.allowed_domains', [
            'open-api.tiktokglobalshop.com',
            'open-api-sandbox.tiktokglobalshop.com',
        ]);
    }

    /**
     * Test connection to TikTok API.
     */
    public function testConnection(): bool
    {
        try {
            return $this->circuitBreaker->call(function () {
                $response = $this->makeRequest('GET', '/api/orders/search', [
                    'shop_id' => $this->credentials['shop_id'],
                    'page_size' => 1,
                    'create_time_from' => now()->subMinute()->timestamp,
                    'create_time_to' => now()->timestamp
                ]);
                
                if (!$response->successful()) {
                    Log::warning('TikTok connection test failed', [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    return false;
                }

                $data = $response->json();
                
                // Check for API errors
                if (isset($data['code']) && $data['code'] !== 0) {
                    Log::warning('TikTok API error in connection test', [
                        'code' => $data['code'],
                        'message' => $data['message'] ?? 'Unknown error'
                    ]);
                    return false;
                }

                return true;
            });
        } catch (Exception $e) {
            Log::error('TikTok connection test exception', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Fetch orders from TikTok.
     */
    public function fetchOrders(Carbon $since = null): Collection
    {
        try {
            return $this->circuitBreaker->call(function () use ($since) {
                $orders = collect();
                $cursor = '';
                $pageSize = 50; // TikTok recommended page size
                
                do {
                    $params = [
                        'shop_id' => $this->credentials['shop_id'],
                        'page_size' => $pageSize,
                        'sort_type' => 1, // Sort by create time
                        'sort_order' => 0, // Descending
                    ];

                    if ($since) {
                        $params['create_time_from'] = $since->timestamp;
                        $params['create_time_to'] = now()->timestamp;
                    }

                    if ($cursor) {
                        $params['cursor'] = $cursor;
                    }

                    $response = $this->makeRequest('GET', '/api/orders/search', $params);
                    
                    if (!$response->successful()) {
                        throw new Exception("TikTok API error: " . $response->body());
                    }

                    $data = $response->json();
                    
                    if (isset($data['code']) && $data['code'] !== 0) {
                        throw new Exception("TikTok API error: " . ($data['message'] ?? 'Unknown error'));
                    }

                    $orderList = $data['data']['orders'] ?? [];
                    
                    if (empty($orderList)) {
                        break;
                    }

                    foreach ($orderList as $orderData) {
                        $orders->push($this->normalizeOrderData($orderData));
                    }

                    $cursor = $data['data']['next_cursor'] ?? '';
                    
                } while (!empty($cursor) && $orders->count() < 1000); // Safety limit

                Log::info('TikTok orders fetched', [
                    'count' => $orders->count(),
                    'since' => $since?->toISOString()
                ]);

                return $orders;
            });
        } catch (Exception $e) {
            Log::error('Failed to fetch TikTok orders', [
                'error' => $e->getMessage(),
                'since' => $since?->toISOString()
            ]);
            return collect();
        }
    }

    /**
     * Update order status on TikTok.
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        try {
            return $this->circuitBreaker->call(function () use ($orderId, $status) {
                $tikTokStatus = $this->mapStatusToTikTok($status);
                
                if (!$tikTokStatus) {
                    Log::warning('Cannot map status to TikTok', [
                        'order_id' => $orderId,
                        'status' => $status
                    ]);
                    return false;
                }

                // TikTok uses different endpoints for different status updates
                $endpoint = match ($tikTokStatus) {
                    'AWAITING_SHIPMENT' => '/api/fulfillment/ship',
                    'SHIPPED' => '/api/fulfillment/ship',
                    'DELIVERED' => '/api/fulfillment/deliver',
                    default => null
                };

                if (!$endpoint) {
                    Log::warning('No TikTok endpoint for status', [
                        'order_id' => $orderId,
                        'status' => $tikTokStatus
                    ]);
                    return false;
                }

                $params = [
                    'shop_id' => $this->credentials['shop_id'],
                    'order_id' => $orderId,
                ];

                if ($tikTokStatus === 'SHIPPED') {
                    $params['tracking_number'] = '';
                    $params['provider_id'] = 'OTHER';
                }

                $response = $this->makeRequest('POST', $endpoint, $params);

                if (!$response->successful()) {
                    Log::error('Failed to update TikTok order status', [
                        'order_id' => $orderId,
                        'status' => $status,
                        'response' => $response->body()
                    ]);
                    return false;
                }

                $data = $response->json();
                
                if (isset($data['code']) && $data['code'] !== 0) {
                    Log::error('TikTok order status update error', [
                        'order_id' => $orderId,
                        'error' => $data['message'] ?? 'Unknown error'
                    ]);
                    return false;
                }

                return true;
            });
        } catch (Exception $e) {
            Log::error('Exception updating TikTok order status', [
                'order_id' => $orderId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Transform TikTok order data to normalized format.
     */
    protected function normalizeOrderData(array $orderData): array
    {
        $recipientAddress = $orderData['recipient_address'] ?? [];
        
        return [
            'platform_order_id' => $orderData['order_id'] ?? '',
            'platform_type' => 'tiktok',
            'customer_name' => $recipientAddress['name'] ?? '',
            'customer_email' => $orderData['buyer_email'] ?? '',
            'customer_phone' => $recipientAddress['phone_number'] ?? '',
            'total_amount' => (float) (($orderData['payment']['total_amount'] ?? 0) / 100), // TikTok uses cents
            'currency' => $orderData['payment']['currency'] ?? 'USD',
            'status' => $this->mapTikTokStatus($orderData['order_status'] ?? ''),
            'order_date' => isset($orderData['create_time']) 
                ? Carbon::createFromTimestamp($orderData['create_time']) 
                : now(),
            'items' => $this->normalizeOrderItems($orderData['order_lines'] ?? []),
            'shipping_address' => $this->normalizeShippingAddress($recipientAddress),
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
                'sku' => $item['product_id'] ?? '',
                'name' => $item['product_name'] ?? '',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'price' => (float) (($item['sale_price'] ?? 0) / 100), // TikTok uses cents
                'variation' => $item['sku_name'] ?? '',
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
            'phone' => $address['phone_number'] ?? '',
            'address_line_1' => $address['address_line1'] ?? '',
            'address_line_2' => $address['address_line2'] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'country' => $address['region_code'] ?? '',
            'postal_code' => $address['postal_code'] ?? '',
        ];
    }

    /**
     * Map internal status to TikTok status.
     */
    private function mapStatusToTikTok(string $status): ?string
    {
        return match ($status) {
            'processing' => 'AWAITING_SHIPMENT',
            'shipped' => 'SHIPPED',
            'delivered' => 'DELIVERED',
            'completed' => 'DELIVERED',
            'cancelled' => 'CANCELLED',
            default => null
        };
    }

    /**
     * Map TikTok status to internal status.
     */
    private function mapTikTokStatus(string $tikTokStatus): string
    {
        return match ($tikTokStatus) {
            'UNPAID' => 'pending_payment',
            'AWAITING_SHIPMENT' => 'processing',
            'AWAITING_COLLECTION' => 'processing',
            'IN_TRANSIT' => 'shipped',
            'SHIPPED' => 'shipped',
            'DELIVERED' => 'delivered',
            'COMPLETED' => 'completed',
            'CANCELLED' => 'cancelled',
            default => 'unknown'
        };
    }

    /**
     * Get platform-specific configuration schema.
     */
    public function getConfigurationSchema(): array
    {
        return [
            'app_key' => [
                'type' => 'string',
                'required' => true,
                'description' => 'TikTok App Key',
                'validation' => 'min:16'
            ],
            'app_secret' => [
                'type' => 'string',
                'required' => true,
                'description' => 'TikTok App Secret',
                'validation' => 'min:32'
            ],
            'access_token' => [
                'type' => 'string',
                'required' => true,
                'description' => 'TikTok Access Token',
                'validation' => 'min:32'
            ],
            'shop_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'TikTok Shop ID',
                'validation' => 'numeric'
            ],
            'warehouse_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Default warehouse ID for fulfillments'
            ]
        ];
    }

    /**
     * Verify TikTok webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        // TikTok uses HMAC-SHA256 for webhook signature verification
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
}