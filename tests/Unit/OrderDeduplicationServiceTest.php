<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OrderDeduplicationService;
use App\Services\OrderConflictResolver;
use Illuminate\Support\Collection;
use Mockery;

class OrderDeduplicationServiceTest extends TestCase
{
    private OrderDeduplicationService $deduplicationService;
    private $mockConflictResolver;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockConflictResolver = Mockery::mock(OrderConflictResolver::class);
        $this->deduplicationService = new OrderDeduplicationService($this->mockConflictResolver);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_detect_duplicates_by_email_and_amount()
    {
        $orders = collect([
            [
                'platform_order_id' => 'SP123',
                'platform_type' => 'shopee',
                'customer_email' => 'test@example.com',
                'total_amount' => 50.00,
                'order_date' => '2022-01-01 10:00:00'
            ],
            [
                'platform_order_id' => 'LZ456',
                'platform_type' => 'lazada',
                'customer_email' => 'test@example.com',
                'total_amount' => 50.00,
                'order_date' => '2022-01-01 10:30:00'
            ],
            [
                'platform_order_id' => 'SP789',
                'platform_type' => 'shopee',
                'customer_email' => 'different@example.com',
                'total_amount' => 75.00,
                'order_date' => '2022-01-01 11:00:00'
            ]
        ]);

        $this->mockConflictResolver
            ->shouldReceive('detectConflicts')
            ->once()
            ->andReturn([]);

        $this->mockConflictResolver
            ->shouldReceive('resolveConflicts')
            ->once()
            ->andReturn(['success' => true]);

        $results = $this->deduplicationService->detectAndResolveDuplicates($orders);

        $this->assertEquals(3, $results['total_orders']);
        $this->assertEquals(1, $results['duplicates_found']);
        $this->assertEquals(1, $results['duplicates_resolved']);
    }

    public function test_detect_duplicates_by_phone_and_amount()
    {
        $orders = collect([
            [
                'platform_order_id' => 'SP123',
                'platform_type' => 'shopee',
                'customer_phone' => '+60123456789',
                'customer_email' => '',
                'total_amount' => 25.50,
                'order_date' => '2022-01-01 14:00:00'
            ],
            [
                'platform_order_id' => 'TT456',
                'platform_type' => 'tiktok',
                'customer_phone' => '60123456789', // Same phone, different format
                'customer_email' => '',
                'total_amount' => 25.50,
                'order_date' => '2022-01-01 14:15:00'
            ]
        ]);

        $this->mockConflictResolver
            ->shouldReceive('detectConflicts')
            ->once()
            ->andReturn([]);

        $this->mockConflictResolver
            ->shouldReceive('resolveConflicts')
            ->once()
            ->andReturn(['success' => true]);

        $results = $this->deduplicationService->detectAndResolveDuplicates($orders);

        $this->assertEquals(2, $results['total_orders']);
        $this->assertEquals(1, $results['duplicates_found']);
        $this->assertEquals(1, $results['duplicates_resolved']);
    }

    public function test_detect_duplicates_by_name_and_address()
    {
        $orders = collect([
            [
                'platform_order_id' => 'SP123',
                'platform_type' => 'shopee',
                'customer_name' => 'John Doe',
                'customer_email' => '',
                'customer_phone' => '',
                'shipping_address' => '123 Main Street, City, State',
                'total_amount' => 100.00,
                'order_date' => '2022-01-01 09:00:00'
            ],
            [
                'platform_order_id' => 'LZ456',
                'platform_type' => 'lazada',
                'customer_name' => 'john doe', // Same name, different case
                'customer_email' => '',
                'customer_phone' => '',
                'shipping_address' => '123 Main St, City, State', // Similar address
                'total_amount' => 100.00,
                'order_date' => '2022-01-01 09:45:00'
            ]
        ]);

        $this->mockConflictResolver
            ->shouldReceive('detectConflicts')
            ->once()
            ->andReturn([]);

        $this->mockConflictResolver
            ->shouldReceive('resolveConflicts')
            ->once()
            ->andReturn(['success' => true]);

        $results = $this->deduplicationService->detectAndResolveDuplicates($orders);

        $this->assertEquals(2, $results['total_orders']);
        $this->assertEquals(1, $results['duplicates_found']);
    }

    public function test_no_duplicates_found()
    {
        $orders = collect([
            [
                'platform_order_id' => 'SP123',
                'platform_type' => 'shopee',
                'customer_email' => 'user1@example.com',
                'total_amount' => 50.00,
                'order_date' => '2022-01-01 10:00:00'
            ],
            [
                'platform_order_id' => 'LZ456',
                'platform_type' => 'lazada',
                'customer_email' => 'user2@example.com',
                'total_amount' => 75.00,
                'order_date' => '2022-01-01 11:00:00'
            ]
        ]);

        $results = $this->deduplicationService->detectAndResolveDuplicates($orders);

        $this->assertEquals(2, $results['total_orders']);
        $this->assertEquals(0, $results['duplicates_found']);
        $this->assertEquals(0, $results['duplicates_resolved']);
    }

    public function test_cross_platform_duplicate_resolution()
    {
        $orders = collect([
            [
                'platform_order_id' => 'SP123',
                'platform_type' => 'shopee',
                'customer_email' => 'test@example.com',
                'total_amount' => 50.00,
                'order_date' => '2022-01-01 10:00:00'
            ],
            [
                'platform_order_id' => 'LZ456',
                'platform_type' => 'lazada',
                'customer_email' => 'test@example.com',
                'total_amount' => 50.00,
                'order_date' => '2022-01-01 10:30:00'
            ]
        ]);

        // Mock conflict detection and resolution
        $this->mockConflictResolver
            ->shouldReceive('detectConflicts')
            ->once()
            ->andReturn(['status' => ['primary' => 'confirmed', 'secondary' => 'pending']]);

        $this->mockConflictResolver
            ->shouldReceive('resolveConflicts')
            ->once()
            ->andReturn(['success' => true]);

        $results = $this->deduplicationService->detectAndResolveDuplicates($orders);

        $this->assertEquals(1, $results['duplicates_found']);
        $this->assertEquals(1, $results['conflicts_detected']);
    }

    public function test_same_platform_duplicate_resolution()
    {
        $orders = collect([
            [
                'platform_order_id' => 'SP123',
                'platform_type' => 'shopee',
                'customer_email' => 'test@example.com',
                'total_amount' => 50.00,
                'order_date' => '2022-01-01 10:00:00'
            ],
            [
                'platform_order_id' => 'SP456',
                'platform_type' => 'shopee',
                'customer_email' => 'test@example.com',
                'total_amount' => 50.00,
                'order_date' => '2022-01-01 10:30:00' // Later order
            ]
        ]);

        $results = $this->deduplicationService->detectAndResolveDuplicates($orders);

        $this->assertEquals(1, $results['duplicates_found']);
        $this->assertEquals(1, $results['duplicates_resolved']);
        $this->assertEquals(0, $results['conflicts_detected']); // Same platform, no conflicts expected
    }

    public function test_phone_normalization()
    {
        // Test that different phone formats are recognized as the same
        $orders = collect([
            [
                'platform_order_id' => 'SP123',
                'platform_type' => 'shopee',
                'customer_phone' => '+60-123-456-789',
                'customer_email' => '',
                'total_amount' => 30.00,
                'order_date' => '2022-01-01 12:00:00'
            ],
            [
                'platform_order_id' => 'LZ456',
                'platform_type' => 'lazada',
                'customer_phone' => '60 123 456 789',
                'customer_email' => '',
                'total_amount' => 30.00,
                'order_date' => '2022-01-01 12:15:00'
            ]
        ]);

        $this->mockConflictResolver
            ->shouldReceive('detectConflicts')
            ->once()
            ->andReturn([]);

        $this->mockConflictResolver
            ->shouldReceive('resolveConflicts')
            ->once()
            ->andReturn(['success' => true]);

        $results = $this->deduplicationService->detectAndResolveDuplicates($orders);

        $this->assertEquals(1, $results['duplicates_found']);
    }

    public function test_name_normalization()
    {
        // Test that different name formats are recognized as similar
        $orders = collect([
            [
                'platform_order_id' => 'SP123',
                'platform_type' => 'shopee',
                'customer_name' => 'John Doe',
                'customer_email' => '',
                'customer_phone' => '',
                'shipping_address' => '123 Main St',
                'total_amount' => 40.00,
                'order_date' => '2022-01-01 13:00:00'
            ],
            [
                'platform_order_id' => 'LZ456',
                'platform_type' => 'lazada',
                'customer_name' => 'JOHN DOE',
                'customer_email' => '',
                'customer_phone' => '',
                'shipping_address' => '123 Main Street',
                'total_amount' => 40.00,
                'order_date' => '2022-01-01 13:20:00'
            ]
        ]);

        $this->mockConflictResolver
            ->shouldReceive('detectConflicts')
            ->once()
            ->andReturn([]);

        $this->mockConflictResolver
            ->shouldReceive('resolveConflicts')
            ->once()
            ->andReturn(['success' => true]);

        $results = $this->deduplicationService->detectAndResolveDuplicates($orders);

        $this->assertEquals(1, $results['duplicates_found']);
    }

    public function test_conflict_resolution_failure()
    {
        $orders = collect([
            [
                'platform_order_id' => 'SP123',
                'platform_type' => 'shopee',
                'customer_email' => 'test@example.com',
                'total_amount' => 50.00,
                'order_date' => '2022-01-01 10:00:00'
            ],
            [
                'platform_order_id' => 'LZ456',
                'platform_type' => 'lazada',
                'customer_email' => 'test@example.com',
                'total_amount' => 50.00,
                'order_date' => '2022-01-01 10:30:00'
            ]
        ]);

        $this->mockConflictResolver
            ->shouldReceive('detectConflicts')
            ->once()
            ->andReturn(['status' => ['primary' => 'delivered', 'secondary' => 'cancelled']]);

        $this->mockConflictResolver
            ->shouldReceive('resolveConflicts')
            ->once()
            ->andReturn(['success' => false]); // Resolution failed

        $results = $this->deduplicationService->detectAndResolveDuplicates($orders);

        $this->assertEquals(1, $results['duplicates_found']);
        $this->assertEquals(0, $results['duplicates_resolved']); // Failed to resolve
        $this->assertEquals(1, $results['conflicts_detected']);
    }
}