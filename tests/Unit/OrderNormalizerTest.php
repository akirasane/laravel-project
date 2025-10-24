<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OrderNormalizer;
use Carbon\Carbon;
use Exception;

class OrderNormalizerTest extends TestCase
{
    private OrderNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new OrderNormalizer();
    }

    public function test_normalize_shopee_order()
    {
        $rawOrder = [
            'order_sn' => 'SP123456789',
            'recipient_address' => [
                'name' => 'John Doe',
                'phone' => '+60123456789',
                'full_address' => '123 Main St, Kuala Lumpur',
                'city' => 'Kuala Lumpur',
                'state' => 'Selangor',
                'country' => 'Malaysia',
                'zipcode' => '50000'
            ],
            'total_amount' => 5000000, // Shopee uses micro-currency
            'currency' => 'MYR',
            'order_status' => 'READY_TO_SHIP',
            'create_time' => 1640995200 // 2022-01-01 00:00:00 UTC
        ];

        $normalized = $this->normalizer->normalize($rawOrder, 'shopee');

        $this->assertEquals('SP123456789', $normalized['platform_order_id']);
        $this->assertEquals('shopee', $normalized['platform_type']);
        $this->assertEquals('John Doe', $normalized['customer_name']);
        $this->assertEquals('', $normalized['customer_email']); // Shopee doesn't provide email
        $this->assertEquals('+60123456789', $normalized['customer_phone']);
        $this->assertEquals(50.0, $normalized['total_amount']); // Converted from micro-currency
        $this->assertEquals('MYR', $normalized['currency']);
        $this->assertEquals('confirmed', $normalized['status']); // READY_TO_SHIP mapped to confirmed
        $this->assertEquals('new', $normalized['workflow_status']);
        $this->assertEquals('synced', $normalized['sync_status']);
        $this->assertInstanceOf(Carbon::class, $normalized['order_date']);
    }

    public function test_normalize_lazada_order()
    {
        $rawOrder = [
            'order_number' => 'LZ987654321',
            'address_shipping' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'phone' => '0123456789',
                'address1' => '456 Oak Ave',
                'city' => 'Bangkok',
                'state' => 'Bangkok',
                'country' => 'Thailand',
                'post_code' => '10100'
            ],
            'customer_email' => 'jane.smith@example.com',
            'price' => 75.50,
            'currency' => 'THB',
            'statuses' => ['ready_to_ship'],
            'created_at' => '2022-01-01T12:00:00Z'
        ];

        $normalized = $this->normalizer->normalize($rawOrder, 'lazada');

        $this->assertEquals('LZ987654321', $normalized['platform_order_id']);
        $this->assertEquals('lazada', $normalized['platform_type']);
        $this->assertEquals('Jane Smith', $normalized['customer_name']);
        $this->assertEquals('jane.smith@example.com', $normalized['customer_email']);
        $this->assertEquals('0123456789', $normalized['customer_phone']);
        $this->assertEquals(75.50, $normalized['total_amount']);
        $this->assertEquals('THB', $normalized['currency']);
        $this->assertEquals('confirmed', $normalized['status']);
    }

    public function test_normalize_shopify_order()
    {
        $rawOrder = [
            'id' => 'SH555666777',
            'customer' => [
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'email' => 'bob.johnson@example.com',
                'phone' => '+1234567890'
            ],
            'total_price' => '99.99',
            'currency' => 'USD',
            'financial_status' => 'paid',
            'fulfillment_status' => 'unfulfilled',
            'created_at' => '2022-01-01T15:30:00Z',
            'shipping_address' => [
                'address1' => '789 Pine St',
                'city' => 'New York',
                'province' => 'NY',
                'country' => 'USA',
                'zip' => '10001'
            ],
            'note' => 'Please handle with care'
        ];

        $normalized = $this->normalizer->normalize($rawOrder, 'shopify');

        $this->assertEquals('SH555666777', $normalized['platform_order_id']);
        $this->assertEquals('shopify', $normalized['platform_type']);
        $this->assertEquals('Bob Johnson', $normalized['customer_name']);
        $this->assertEquals('bob.johnson@example.com', $normalized['customer_email']);
        $this->assertEquals(99.99, $normalized['total_amount']);
        $this->assertEquals('confirmed', $normalized['status']); // unfulfilled maps to confirmed
        $this->assertEquals('Please handle with care', $normalized['notes']);
    }

    public function test_normalize_tiktok_order()
    {
        $rawOrder = [
            'order_id' => 'TT111222333',
            'recipient_info' => [
                'name' => 'Alice Brown',
                'phone' => '+65987654321',
                'address_line1' => '321 Orchard Rd',
                'city' => 'Singapore',
                'country' => 'Singapore',
                'postal_code' => '238123'
            ],
            'buyer_email' => 'alice.brown@example.com',
            'payment_info' => [
                'total_amount' => 45.75,
                'currency' => 'SGD'
            ],
            'order_status' => 'AWAITING_SHIPMENT',
            'create_time' => 1640995200,
            'buyer_message' => 'Urgent delivery needed'
        ];

        $normalized = $this->normalizer->normalize($rawOrder, 'tiktok');

        $this->assertEquals('TT111222333', $normalized['platform_order_id']);
        $this->assertEquals('tiktok', $normalized['platform_type']);
        $this->assertEquals('Alice Brown', $normalized['customer_name']);
        $this->assertEquals('alice.brown@example.com', $normalized['customer_email']);
        $this->assertEquals(45.75, $normalized['total_amount']);
        $this->assertEquals('confirmed', $normalized['status']); // AWAITING_SHIPMENT maps to confirmed
        $this->assertEquals('Urgent delivery needed', $normalized['notes']);
    }

    public function test_normalize_unsupported_platform_throws_exception()
    {
        $rawOrder = ['order_id' => 'TEST123'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported platform type: unsupported');

        $this->normalizer->normalize($rawOrder, 'unsupported');
    }

    public function test_normalize_invalid_order_data_throws_exception()
    {
        $rawOrder = []; // Missing required fields

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Order validation failed/');

        $this->normalizer->normalize($rawOrder, 'shopee');
    }

    public function test_batch_normalize_with_mixed_results()
    {
        $rawOrders = [
            [
                'order_sn' => 'SP123',
                'recipient_address' => ['name' => 'Test User'],
                'total_amount' => 1000000,
                'currency' => 'USD',
                'order_status' => 'READY_TO_SHIP',
                'create_time' => 1640995200
            ],
            [], // Invalid order - missing required fields
            [
                'order_sn' => 'SP456',
                'recipient_address' => ['name' => 'Another User'],
                'total_amount' => 2000000,
                'currency' => 'USD',
                'order_status' => 'SHIPPED',
                'create_time' => 1640995200
            ]
        ];

        $normalized = $this->normalizer->batchNormalize($rawOrders, 'shopee');

        // Should have 2 successful normalizations (first and third orders)
        $this->assertCount(2, $normalized);
        $this->assertEquals('SP123', $normalized[0]['platform_order_id']);
        $this->assertEquals('SP456', $normalized[1]['platform_order_id']);
    }

    public function test_status_mapping_shopee()
    {
        $statusMappings = [
            'UNPAID' => 'pending',
            'READY_TO_SHIP' => 'confirmed',
            'SHIPPED' => 'shipped',
            'DELIVERED' => 'delivered',
            'COMPLETED' => 'delivered',
            'CANCELLED' => 'cancelled'
        ];

        foreach ($statusMappings as $shopeeStatus => $expectedStatus) {
            $rawOrder = [
                'order_sn' => 'SP123',
                'recipient_address' => ['name' => 'Test'],
                'total_amount' => 1000000,
                'currency' => 'USD',
                'order_status' => $shopeeStatus,
                'create_time' => 1640995200
            ];

            $normalized = $this->normalizer->normalize($rawOrder, 'shopee');
            $this->assertEquals($expectedStatus, $normalized['status']);
        }
    }

    public function test_address_normalization()
    {
        $rawOrder = [
            'order_sn' => 'SP123',
            'recipient_address' => [
                'name' => 'Test User',
                'full_address' => '123 Main St',
                'city' => 'Test City',
                'state' => 'Test State',
                'country' => 'Test Country',
                'zipcode' => '12345'
            ],
            'total_amount' => 1000000,
            'currency' => 'USD',
            'order_status' => 'READY_TO_SHIP',
            'create_time' => 1640995200
        ];

        $normalized = $this->normalizer->normalize($rawOrder, 'shopee');
        
        $expectedAddress = '123 Main St, Test City, Test State, Test Country, 12345';
        $this->assertEquals($expectedAddress, $normalized['shipping_address']);
    }
}