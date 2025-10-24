<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\WorkflowConditionEvaluator;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkflowConditionEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowConditionEvaluator $evaluator;
    protected Order $testOrder;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->evaluator = new WorkflowConditionEvaluator();
        
        $this->testOrder = Order::factory()->create([
            'platform_order_id' => 'TEST-001',
            'platform_type' => 'shopee',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total_amount' => 150.00,
            'currency' => 'USD',
            'status' => 'pending',
            'raw_data' => [
                'payment_method' => 'credit_card',
                'priority' => 'high',
                'shipping_method' => 'express',
            ],
        ]);

        // Create order items
        OrderItem::factory()->count(3)->create([
            'order_id' => $this->testOrder->id,
            'quantity' => 2,
        ]);
    }

    public function test_evaluates_simple_equality_condition()
    {
        $conditions = [
            [
                'field' => 'platform_type',
                'operator' => '=',
                'value' => 'shopee',
            ],
        ];

        $result = $this->evaluator->evaluate($this->testOrder, $conditions);
        $this->assertTrue($result);

        // Test with different value
        $conditions[0]['value'] = 'lazada';
        $result = $this->evaluator->evaluate($this->testOrder, $conditions);
        $this->assertFalse($result);
    }

    public function test_evaluates_numeric_comparison_conditions()
    {
        // Greater than
        $conditions = [
            [
                'field' => 'total_amount',
                'operator' => '>',
                'value' => 100,
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        // Less than
        $conditions[0]['operator'] = '<';
        $conditions[0]['value'] = 200;
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        // Greater than or equal
        $conditions[0]['operator'] = '>=';
        $conditions[0]['value'] = 150;
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        // Less than or equal
        $conditions[0]['operator'] = '<=';
        $conditions[0]['value'] = 150;
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_string_conditions()
    {
        // Contains
        $conditions = [
            [
                'field' => 'customer_name',
                'operator' => 'contains',
                'value' => 'John',
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        // Starts with
        $conditions[0]['operator'] = 'starts_with';
        $conditions[0]['value'] = 'John';
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        // Ends with
        $conditions[0]['operator'] = 'ends_with';
        $conditions[0]['value'] = 'Doe';
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_in_array_conditions()
    {
        $conditions = [
            [
                'field' => 'platform_type',
                'operator' => 'in',
                'value' => ['shopee', 'lazada', 'shopify'],
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        // Not in array
        $conditions[0]['operator'] = 'not_in';
        $conditions[0]['value'] = ['tiktok', 'amazon'];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_raw_data_conditions()
    {
        $conditions = [
            [
                'field' => 'raw_data.payment_method',
                'operator' => '=',
                'value' => 'credit_card',
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        $conditions = [
            [
                'field' => 'raw_data.priority',
                'operator' => '=',
                'value' => 'high',
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_order_items_count()
    {
        $conditions = [
            [
                'field' => 'order_items_count',
                'operator' => '=',
                'value' => 3,
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        $conditions[0]['operator'] = '>';
        $conditions[0]['value'] = 2;
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_order_items_total_quantity()
    {
        $conditions = [
            [
                'field' => 'order_items_total_quantity',
                'operator' => '=',
                'value' => 6, // 3 items * 2 quantity each
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_null_conditions()
    {
        // Test is_null
        $conditions = [
            [
                'field' => 'notes', // This field is null in our test order
                'operator' => 'is_null',
                'value' => null,
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        // Test is_not_null
        $conditions[0]['field'] = 'customer_name';
        $conditions[0]['operator'] = 'is_not_null';
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_empty_conditions()
    {
        // Test is_empty
        $conditions = [
            [
                'field' => 'notes',
                'operator' => 'is_empty',
                'value' => null,
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        // Test is_not_empty
        $conditions[0]['field'] = 'customer_name';
        $conditions[0]['operator'] = 'is_not_empty';
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_between_conditions()
    {
        $conditions = [
            [
                'field' => 'total_amount',
                'operator' => 'between',
                'value' => [100, 200],
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        // Test not_between
        $conditions[0]['operator'] = 'not_between';
        $conditions[0]['value'] = [200, 300];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_regex_conditions()
    {
        $conditions = [
            [
                'field' => 'customer_email',
                'operator' => 'regex',
                'value' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_multiple_conditions_with_and_logic()
    {
        $conditions = [
            [
                'field' => 'platform_type',
                'operator' => '=',
                'value' => 'shopee',
            ],
            [
                'field' => 'total_amount',
                'operator' => '>',
                'value' => 100,
            ],
        ];

        // Both conditions should pass
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));

        // Make one condition fail
        $conditions[1]['value'] = 200;
        $this->assertFalse($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_grouped_conditions_with_or_logic()
    {
        $conditions = [
            'operator' => 'OR',
            'conditions' => [
                [
                    'field' => 'platform_type',
                    'operator' => '=',
                    'value' => 'lazada', // This will fail
                ],
                [
                    'field' => 'total_amount',
                    'operator' => '>',
                    'value' => 100, // This will pass
                ],
            ],
        ];

        // Should pass because one condition passes with OR logic
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_evaluates_nested_grouped_conditions()
    {
        $conditions = [
            'operator' => 'AND',
            'conditions' => [
                [
                    'field' => 'platform_type',
                    'operator' => '=',
                    'value' => 'shopee',
                ],
                [
                    'operator' => 'OR',
                    'conditions' => [
                        [
                            'field' => 'total_amount',
                            'operator' => '>',
                            'value' => 200, // This will fail
                        ],
                        [
                            'field' => 'currency',
                            'operator' => '=',
                            'value' => 'USD', // This will pass
                        ],
                    ],
                ],
            ],
        ];

        // Should pass: platform_type = shopee AND (total_amount > 200 OR currency = USD)
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_returns_true_for_empty_conditions()
    {
        $this->assertTrue($this->evaluator->evaluate($this->testOrder, []));
    }

    public function test_handles_invalid_operators_gracefully()
    {
        $conditions = [
            [
                'field' => 'platform_type',
                'operator' => 'invalid_operator',
                'value' => 'shopee',
            ],
        ];

        $this->assertFalse($this->evaluator->evaluate($this->testOrder, $conditions));
    }

    public function test_gets_available_operators()
    {
        $operators = WorkflowConditionEvaluator::getAvailableOperators();
        
        $this->assertIsArray($operators);
        $this->assertArrayHasKey('=', $operators);
        $this->assertArrayHasKey('>', $operators);
        $this->assertArrayHasKey('contains', $operators);
        $this->assertArrayHasKey('between', $operators);
    }

    public function test_gets_available_fields()
    {
        $fields = WorkflowConditionEvaluator::getAvailableFields();
        
        $this->assertIsArray($fields);
        $this->assertArrayHasKey('platform_type', $fields);
        $this->assertArrayHasKey('total_amount', $fields);
        $this->assertArrayHasKey('order_items_count', $fields);
        $this->assertArrayHasKey('raw_data.payment_method', $fields);
    }
}