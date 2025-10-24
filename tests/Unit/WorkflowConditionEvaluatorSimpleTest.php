<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\WorkflowConditionEvaluator;

class WorkflowConditionEvaluatorSimpleTest extends TestCase
{
    protected WorkflowConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new WorkflowConditionEvaluator();
    }

    public function test_returns_true_for_empty_conditions()
    {
        $mockOrder = $this->createMockOrder();
        $result = $this->evaluator->evaluate($mockOrder, []);
        $this->assertTrue($result);
    }

    public function test_gets_available_operators()
    {
        $operators = WorkflowConditionEvaluator::getAvailableOperators();
        
        $this->assertIsArray($operators);
        $this->assertArrayHasKey('=', $operators);
        $this->assertArrayHasKey('>', $operators);
        $this->assertArrayHasKey('contains', $operators);
        $this->assertArrayHasKey('between', $operators);
        $this->assertEquals('Equals', $operators['=']);
        $this->assertEquals('Greater than', $operators['>']);
    }

    public function test_gets_available_fields()
    {
        $fields = WorkflowConditionEvaluator::getAvailableFields();
        
        $this->assertIsArray($fields);
        $this->assertArrayHasKey('platform_type', $fields);
        $this->assertArrayHasKey('total_amount', $fields);
        $this->assertArrayHasKey('order_items_count', $fields);
        $this->assertArrayHasKey('raw_data.payment_method', $fields);
        $this->assertEquals('Platform Type', $fields['platform_type']);
        $this->assertEquals('Total Amount', $fields['total_amount']);
    }

    private function createMockOrder()
    {
        return new class {
            public $platform_type = 'shopee';
            public $total_amount = 150.00;
            public $customer_name = 'John Doe';
            public $raw_data = [
                'payment_method' => 'credit_card',
                'priority' => 'high',
            ];

            public function orderItems()
            {
                return new class {
                    public function count() { return 3; }
                    public function sum($field) { return 6; }
                };
            }
        };
    }
}