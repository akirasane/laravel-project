<?php

namespace App\Services\PlatformConnectors;

use App\Services\PlatformCredentialManager;
use App\Services\CircuitBreakerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Carbon\Carbon;
use Exception;

class ShopifyConnector extends AbstractPlatformConnector
{
    private CircuitBreakerService $circuitBreaker;

    public function __construct(PlatformCredentialManager $credentialManager)
    {
        parent::__construct($credentialManager);
        $this->circuitBreaker = app(CircuitBreakerService::class, ['serviceName' => 'shopify']);
    }

    /**
     * Initialize Shopify-specific configuration.
     */
    protected function initializePlatformConfig(): void
    {
        $this->platformType = 'shopify';
        $this->rateLimits = config('platforms.rate_limits.shopify', [
            'requests_per_minute' => 40,
            'burst_limit' => 4
        ]);
    }

    /**
     * Get the base API URL for Shopify.
     */
    protected function getBaseUrl(): string
    {
        if (!$this->credentials) {
            $this->loadCredentials();
        }

        $shopDomain = $this->credentials['shop_domain'];
        
        // Ensure the domain includes .myshopify.com if not already present
        if (!str_contains($shopDomain, '.myshopify.com')) {
            $shopDomain .= '.myshopify.com';
        }

        return "https://{$shopDomain}/admin/api/2023-10";
    }

    /**
     * Get Shopify-specific headers for API requests.
     */
    protected function getHeaders(): array
    {
        if (!$this->credentials) {
            $this->loadCredentials();
        }

        return [
            'Content-Type' => 'application/json',
            'X-Shopify-Access-Token' => $this->credentials['access_token'],
        ];
    }

    /**
     * Get allowed domains for Shopify.
     */
    protected function getAllowedDomains(): array
    {
        return config('platforms.endpoints.shopify.allowed_domains', [
            'myshopify.com',
            'shopify.com',
        ]);
    }

    /**
     * Test connection to Shopify API.
     */
    public function testConnection(): bool
    {
        try {
            if (!$this->loadCredentials()) {
                Log::warning('No credentials available for Shopify connection test');
                return false;
            }

            return $this->circuitBreaker->call(function () {
                $response = $this->makeRequest('GET', '/shop.json');
                
                if (!$response->successful()) {
                    Log::warning('Shopify connection test failed', [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    return false;
                }

                $data = $response->json();
                
                // Check if we got shop data
                if (!isset($data['shop'])) {
                    Log::warning('Shopify connection test: no shop data returned');
                    return false;
                }

                return true;
            });
        } catch (Exception $e) {
            Log::error('Shopify connection test exception', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Fetch orders from Shopify.
     */
    public function fetchOrders(Carbon $since = null): Collection
    {
        try {
            return $this->circuitBreaker->call(function () use ($since) {
                $orders = collect();
                $limit = 250; // Shopify max limit
                $sinceId = null;
                
                do {
                    $params = [
                        'limit' => $limit,
                        'status' => 'any',
                        'fields' => 'id,name,email,created_at,updated_at,total_price,currency,financial_status,fulfillment_status,customer,line_items,shipping_address'
                    ];

                    if ($since) {
                        $params['created_at_min'] = $since->toISOString();
                    }

                    if ($sinceId) {
                        $params['since_id'] = $sinceId;
                    }

                    $response = $this->makeRequest('GET', '/orders.json', $params);
                    
                    if (!$response->successful()) {
                        throw new Exception("Shopify API error: " . $response->body());
                    }

                    $data = $response->json();
                    $orderList = $data['orders'] ?? [];
                    
                    if (empty($orderList)) {
                        break;
                    }

                    foreach ($orderList as $orderData) {
                        $orders->push($this->normalizeOrderData($orderData));
                        $sinceId = $orderData['id']; // Update since_id for pagination
                    }
                    
                } while (count($orderList) === $limit && $orders->count() < 1000); // Safety limit

                Log::info('Shopify orders fetched', [
                    'count' => $orders->count(),
                    'since' => $since?->toISOString()
                ]);

                return $orders;
            });
        } catch (Exception $e) {
            Log::error('Failed to fetch Shopify orders', [
                'error' => $e->getMessage(),
                'since' => $since?->toISOString()
            ]);
            return collect();
        }
    }

    /**
     * Update order status on Shopify.
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        try {
            return $this->circuitBreaker->call(function () use ($orderId, $status) {
                $shopifyStatus = $this->mapStatusToShopify($status);
                
                if (!$shopifyStatus) {
                    Log::warning('Cannot map status to Shopify', [
                        'order_id' => $orderId,
                        'status' => $status
                    ]);
                    return false;
                }

                // For Shopify, we need to create fulfillments to update order status
                if ($shopifyStatus === 'fulfilled') {
                    return $this->createFulfillment($orderId);
                }

                // For other statuses, we might need to update the order directly
                $response = $this->makeRequest('PUT', "/orders/{$orderId}.json", [
                    'order' => [
                        'id' => $orderId,
                        'tags' => "status:{$status}"
                    ]
                ]);

                if (!$response->successful()) {
                    Log::error('Failed to update Shopify order status', [
                        'order_id' => $orderId,
                        'status' => $status,
                        'response' => $response->body()
                    ]);
                    return false;
                }

                return true;
            });
        } catch (Exception $e) {
            Log::error('Exception updating Shopify order status', [
                'order_id' => $orderId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create fulfillment for Shopify order.
     */
    private function createFulfillment(string $orderId): bool
    {
        try {
            // First, get order line items
            $response = $this->makeRequest('GET', "/orders/{$orderId}.json", [
                'fields' => 'line_items'
            ]);

            if (!$response->successful()) {
                return false;
            }

            $orderData = $response->json();
            $lineItems = $orderData['order']['line_items'] ?? [];

            if (empty($lineItems)) {
                return false;
            }

            // Create fulfillment
            $fulfillmentData = [
                'fulfillment' => [
                    'location_id' => $this->credentials['location_id'] ?? null,
                    'tracking_number' => '',
                    'tracking_company' => 'Other',
                    'notify_customer' => true,
                    'line_items' => array_map(function ($item) {
                        return [
                            'id' => $item['id'],
                            'quantity' => $item['quantity']
                        ];
                    }, $lineItems)
                ]
            ];

            $response = $this->makeRequest('POST', "/orders/{$orderId}/fulfillments.json", $fulfillmentData);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to create Shopify fulfillment', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Transform Shopify order data to normalized format.
     */
    protected function normalizeOrderData(array $orderData): array
    {
        $customer = $orderData['customer'] ?? [];
        
        return [
            'platform_order_id' => (string) $orderData['id'],
            'platform_type' => 'shopify',
            'customer_name' => ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''),
            'customer_email' => $orderData['email'] ?? $customer['email'] ?? '',
            'customer_phone' => $customer['phone'] ?? '',
            'total_amount' => (float) ($orderData['total_price'] ?? 0),
            'currency' => $orderData['currency'] ?? 'USD',
            'status' => $this->mapShopifyStatus($orderData),
            'order_date' => isset($orderData['created_at']) 
                ? Carbon::parse($orderData['created_at']) 
                : now(),
            'items' => $this->normalizeOrderItems($orderData['line_items'] ?? []),
            'shipping_address' => $this->normalizeShippingAddress($orderData['shipping_address'] ?? []),
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
                'name' => $item['name'] ?? $item['title'] ?? '',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'price' => (float) ($item['price'] ?? 0),
                'variation' => $item['variant_title'] ?? '',
            ];
        }, $items);
    }

    /**
     * Normalize shipping address.
     */
    private function normalizeShippingAddress(array $address): array
    {
        if (empty($address)) {
            return [];
        }

        return [
            'name' => ($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''),
            'phone' => $address['phone'] ?? '',
            'address_line_1' => $address['address1'] ?? '',
            'address_line_2' => $address['address2'] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['province'] ?? '',
            'country' => $address['country'] ?? '',
            'postal_code' => $address['zip'] ?? '',
        ];
    }

    /**
     * Map internal status to Shopify status.
     */
    private function mapStatusToShopify(string $status): ?string
    {
        return match ($status) {
            'processing' => 'unfulfilled',
            'shipped' => 'partial',
            'delivered' => 'fulfilled',
            'completed' => 'fulfilled',
            'cancelled' => 'cancelled',
            default => null
        };
    }

    /**
     * Map Shopify status to internal status.
     */
    private function mapShopifyStatus(array $orderData): string
    {
        $financialStatus = $orderData['financial_status'] ?? '';
        $fulfillmentStatus = $orderData['fulfillment_status'] ?? '';

        // Check financial status first
        if ($financialStatus === 'pending') {
            return 'pending_payment';
        }

        if ($financialStatus === 'refunded') {
            return 'refunded';
        }

        // Then check fulfillment status
        return match ($fulfillmentStatus) {
            'fulfilled' => 'delivered',
            'partial' => 'shipped',
            'unfulfilled' => 'processing',
            'restocked' => 'cancelled',
            default => 'unknown'
        };
    }

    /**
     * Get platform-specific configuration schema.
     */
    public function getConfigurationSchema(): array
    {
        return [
            'shop_domain' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Shopify shop domain (e.g., mystore.myshopify.com)',
                'validation' => 'regex:/^[a-zA-Z0-9\-]+\.myshopify\.com$/'
            ],
            'access_token' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Shopify Admin API access token',
                'validation' => 'starts_with:shpat_'
            ],
            'api_key' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Shopify API key',
                'validation' => 'min:32'
            ],
            'location_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Default location ID for fulfillments'
            ],
            'webhook_secret' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Webhook secret for signature verification'
            ]
        ];
    }

    /**
     * Verify Shopify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        // Shopify uses HMAC-SHA256 with base64 encoding
        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        
        return hash_equals($expectedSignature, $signature);
    }
}