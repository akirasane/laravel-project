<?php

namespace App\Services\PlatformConnectors;

use App\Services\PlatformCredentialManager;
use App\Services\CircuitBreakerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Carbon\Carbon;
use Exception;

class LazadaConnector extends AbstractPlatformConnector
{
    private CircuitBreakerService $circuitBreaker;

    public function __construct(PlatformCredentialManager $credentialManager)
    {
        parent::__construct($credentialManager);
        $this->circuitBreaker = app(CircuitBreakerService::class, ['serviceName' => 'lazada']);
    }

    /**
     * Initialize Lazada-specific configuration.
     */
    protected function initializePlatformConfig(): void
    {
        $this->platformType = 'lazada';
        $this->rateLimits = config('platforms.rate_limits.lazada', [
            'requests_per_minute' => 60,
            'burst_limit' => 5
        ]);
    }

    /**
     * Get the base API URL for Lazada.
     */
    protected function getBaseUrl(): string
    {
        $endpoints = config('platforms.endpoints.lazada');
        return $this->getEnvironmentEndpoint(
            $endpoints['sandbox'],
            $endpoints['production']
        );
    }

    /**
     * Get Lazada-specific headers for API requests.
     */
    protected function getHeaders(): array
    {
        if (!$this->credentials) {
            $this->loadCredentials();
        }

        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->credentials['access_token'],
        ];
    }

    /**
     * Get allowed domains for Lazada.
     */
    protected function getAllowedDomains(): array
    {
        return config('platforms.endpoints.lazada.allowed_domains', [
            'api.lazada.com',
            'api.lazada.co.th',
            'api.lazada.com.my',
            'api.lazada.sg',
            'api.lazada.com.ph',
            'api.lazada.vn',
        ]);
    }

    /**
     * Test connection to Lazada API.
     */
    public function testConnection(): bool
    {
        try {
            return $this->circuitBreaker->call(function () {
                $params = $this->buildLazadaParams('/orders/get', [
                    'created_after' => now()->subMinute()->toISOString(),
                    'limit' => 1
                ]);

                $response = $this->makeRequest('GET', '/orders/get', $params);
                
                if (!$response->successful()) {
                    Log::warning('Lazada connection test failed', [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    return false;
                }

                $data = $response->json();
                
                // Check for API errors
                if (isset($data['code']) && $data['code'] !== '0') {
                    Log::warning('Lazada API error in connection test', [
                        'code' => $data['code'],
                        'message' => $data['message'] ?? 'Unknown error'
                    ]);
                    return false;
                }

                return true;
            });
        } catch (Exception $e) {
            Log::error('Lazada connection test exception', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Fetch orders from Lazada.
     */
    public function fetchOrders(Carbon $since = null): Collection
    {
        try {
            return $this->circuitBreaker->call(function () use ($since) {
                $orders = collect();
                $offset = 0;
                $limit = 100;
                
                do {
                    $params = [
                        'offset' => $offset,
                        'limit' => $limit,
                        'sort_by' => 'created_at',
                        'sort_direction' => 'DESC'
                    ];

                    if ($since) {
                        $params['created_after'] = $since->toISOString();
                    }

                    $signedParams = $this->buildLazadaParams('/orders/get', $params);
                    $response = $this->makeRequest('GET', '/orders/get', $signedParams);
                    
                    if (!$response->successful()) {
                        throw new Exception("Lazada API error: " . $response->body());
                    }

                    $data = $response->json();
                    
                    if (isset($data['code']) && $data['code'] !== '0') {
                        throw new Exception("Lazada API error: " . ($data['message'] ?? 'Unknown error'));
                    }

                    $orderList = $data['data']['orders'] ?? [];
                    
                    if (empty($orderList)) {
                        break;
                    }

                    foreach ($orderList as $orderData) {
                        $orders->push($this->normalizeOrderData($orderData));
                    }

                    $offset += $limit;
                    
                } while (count($orderList) === $limit && $orders->count() < 1000); // Safety limit

                Log::info('Lazada orders fetched', [
                    'count' => $orders->count(),
                    'since' => $since?->toISOString()
                ]);

                return $orders;
            });
        } catch (Exception $e) {
            Log::error('Failed to fetch Lazada orders', [
                'error' => $e->getMessage(),
                'since' => $since?->toISOString()
            ]);
            return collect();
        }
    }

    /**
     * Update order status on Lazada.
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        try {
            return $this->circuitBreaker->call(function () use ($orderId, $status) {
                $lazadaStatus = $this->mapStatusToLazada($status);
                
                if (!$lazadaStatus) {
                    Log::warning('Cannot map status to Lazada', [
                        'order_id' => $orderId,
                        'status' => $status
                    ]);
                    return false;
                }

                // Lazada uses different endpoints for different status updates
                $endpoint = match ($lazadaStatus) {
                    'ready_to_ship' => '/order/rts',
                    'shipped' => '/order/pack',
                    'delivered' => '/order/fulfill',
                    default => null
                };

                if (!$endpoint) {
                    Log::warning('No Lazada endpoint for status', [
                        'order_id' => $orderId,
                        'status' => $lazadaStatus
                    ]);
                    return false;
                }

                $params = $this->buildLazadaParams($endpoint, [
                    'order_item_ids' => json_encode([$orderId]),
                    'delivery_type' => 'dropship',
                    'shipping_provider' => 'Standard'
                ]);

                $response = $this->makeRequest('POST', $endpoint, $params);

                if (!$response->successful()) {
                    Log::error('Failed to update Lazada order status', [
                        'order_id' => $orderId,
                        'status' => $status,
                        'response' => $response->body()
                    ]);
                    return false;
                }

                $data = $response->json();
                
                if (isset($data['code']) && $data['code'] !== '0') {
                    Log::error('Lazada order status update error', [
                        'order_id' => $orderId,
                        'error' => $data['message'] ?? 'Unknown error'
                    ]);
                    return false;
                }

                return true;
            });
        } catch (Exception $e) {
            Log::error('Exception updating Lazada order status', [
                'order_id' => $orderId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Transform Lazada order data to normalized format.
     */
    protected function normalizeOrderData(array $orderData): array
    {
        return [
            'platform_order_id' => $orderData['order_number'] ?? '',
            'platform_type' => 'lazada',
            'customer_name' => $orderData['address_shipping']['first_name'] . ' ' . $orderData['address_shipping']['last_name'],
            'customer_email' => $orderData['customer_email'] ?? '',
            'customer_phone' => $orderData['address_shipping']['phone'] ?? '',
            'total_amount' => (float) ($orderData['price'] ?? 0),
            'currency' => $orderData['currency'] ?? 'USD',
            'status' => $this->mapLazadaStatus($orderData['statuses'] ?? []),
            'order_date' => isset($orderData['created_at']) 
                ? Carbon::parse($orderData['created_at']) 
                : now(),
            'items' => $this->normalizeOrderItems($orderData['order_items'] ?? []),
            'shipping_address' => $this->normalizeShippingAddress($orderData['address_shipping'] ?? []),
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
                'sku' => $item['sku'] ?? '',
                'name' => $item['name'] ?? '',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'price' => (float) ($item['item_price'] ?? 0),
                'variation' => $item['variation'] ?? '',
            ];
        }, $items);
    }

    /**
     * Normalize shipping address.
     */
    private function normalizeShippingAddress(array $address): array
    {
        return [
            'name' => ($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''),
            'phone' => $address['phone'] ?? '',
            'address_line_1' => $address['address1'] ?? '',
            'address_line_2' => $address['address2'] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'country' => $address['country'] ?? '',
            'postal_code' => $address['post_code'] ?? '',
        ];
    }

    /**
     * Map internal status to Lazada status.
     */
    private function mapStatusToLazada(string $status): ?string
    {
        return match ($status) {
            'processing' => 'ready_to_ship',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'completed' => 'delivered',
            'cancelled' => 'cancelled',
            default => null
        };
    }

    /**
     * Map Lazada status to internal status.
     */
    private function mapLazadaStatus(array $statuses): string
    {
        // Lazada orders can have multiple statuses, get the latest one
        $latestStatus = end($statuses);
        
        return match ($latestStatus) {
            'pending' => 'pending_payment',
            'ready_to_ship' => 'processing',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'canceled' => 'cancelled',
            'returned' => 'returned',
            default => 'unknown'
        };
    }

    /**
     * Build Lazada API parameters with signature.
     */
    private function buildLazadaParams(string $apiPath, array $params = []): array
    {
        $timestamp = (string) (time() * 1000); // Lazada uses milliseconds
        
        $systemParams = [
            'app_key' => $this->credentials['app_key'],
            'timestamp' => $timestamp,
            'sign_method' => 'sha256',
            'format' => 'JSON',
            'v' => '1.0',
            'access_token' => $this->credentials['access_token'],
        ];

        $allParams = array_merge($systemParams, $params);
        ksort($allParams);

        // Build signature string
        $signString = $apiPath;
        foreach ($allParams as $key => $value) {
            $signString .= $key . $value;
        }

        // Generate signature
        $signature = strtoupper(hash_hmac('sha256', $signString, $this->credentials['app_secret']));
        $allParams['sign'] = $signature;

        return $allParams;
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
                'description' => 'Lazada App Key',
                'validation' => 'min:16'
            ],
            'app_secret' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Lazada App Secret',
                'validation' => 'min:32'
            ],
            'access_token' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Lazada Access Token',
                'validation' => 'min:32'
            ],
            'country' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Lazada country code (TH, MY, SG, PH, VN, ID)',
                'validation' => 'in:TH,MY,SG,PH,VN,ID'
            ]
        ];
    }

    /**
     * Verify Lazada webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        // Lazada uses HMAC-SHA256 for webhook signature verification
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
}