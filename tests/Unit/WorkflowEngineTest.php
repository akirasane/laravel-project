<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\WorkflowEngine;
use App\Services\WorkflowConditionEvaluator;
use App\Models\Order;
use App\Models\ProcessFlow;
use App\Models\WorkflowStep;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowEngine $workflowEngine;
    protected User $testUser;
    protected Order $testOrder;

    protected function setUp(): void
    {
        parent::setUp();
        
        $conditionEvaluator = new WorkflowConditionEvaluator();
        $this->workflowEngine = new WorkflowEngine($conditionEvaluator);
        
        // Create test user
        $this->testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        
        // Create test order
        $this->testOrder = Order::factory()->create([
            'platform_order_id' => 'TEST-001',
            'platform_type' => 'shopee',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'workflow_status' => 'new',
        ]);
    }

    public function test_can_create_process_flow_with_steps()
    {
        $this->actingAs($this->testUser);

        $steps = [
            [
                'name' => 'Order Validation',
                'type' => 'automatic',
                'auto_execute' => true,
                'configuration' => ['update_status' => 'validated'],
            ],
            [
                'name' => 'Manual Review',
                'type' => 'manual',
                'assigned_role' => 'reviewer',
                'auto_execute' => false,
            ],
        ];

        $processFlow = $this->workflowEngine->createFlow(
            'Test Workflow',
            'A test workflow for unit testing',
            $steps
        );

        $this->assertInstanceOf(ProcessFlow::class, $processFlow);
        $this->assertEquals('Test Workflow', $processFlow->name);
        $this->assertTrue($processFlow->is_active);
        $this->assertEquals($this->testUser->id, $processFlow->created_by);
        $this->assertCount(2, $processFlow->workflowSteps);

        // Check first step
        $firstStep = $processFlow->workflowSteps->first();
        $this->assertEquals('Order Validation', $firstStep->name);
        $this->assertEquals('automatic', $firstStep->step_type);
        $this->assertTrue($firstStep->auto_execute);
        $this->assertEquals(1, $firstStep->step_order);

        // Check second step
        $secondStep = $processFlow->workflowSteps->last();
        $this->assertEquals('Manual Review', $secondStep->name);
        $this->assertEquals('manual', $secondStep->step_type);
        $this->assertEquals('reviewer', $secondStep->assigned_role);
        $this->assertFalse($secondStep->auto_execute);
        $this->assertEquals(2, $secondStep->step_order);
    }

    public function test_can_execute_automatic_step()
    {
        $processFlow = ProcessFlow::factory()->create();
        $step = WorkflowStep::factory()->create([
            'process_flow_id' => $processFlow->id,
            'step_type' => 'automatic',
            'auto_execute' => true,
            'configuration' => ['update_status' => 'validated'],
        ]);

        $result = $this->workflowEngine->executeStep($this->testOrder, $step);

        $this->assertTrue($result);
        $this->testOrder->refresh();
        $this->assertEquals('validated', $this->testOrder->status);
    }

    public function test_can_execute_manual_step()
    {
        $processFlow = ProcessFlow::factory()->create();
        $step = WorkflowStep::factory()->create([
            'process_flow_id' => $processFlow->id,
            'step_type' => 'manual',
            'assigned_role' => 'reviewer',
            'auto_execute' => false,
        ]);

        $result = $this->workflowEngine->executeStep($this->testOrder, $step);

        $this->assertTrue($result);
        
        // Should create a task assignment
        $taskAssignment = TaskAssignment::where('order_id', $this->testOrder->id)
            ->where('workflow_step_id', $step->id)
            ->first();
        
        $this->assertNotNull($taskAssignment);
        $this->assertEquals('pending', $taskAssignment->status);
    }

    public function test_evaluates_step_conditions()
    {
        $processFlow = ProcessFlow::factory()->create();
        $step = WorkflowStep::factory()->create([
            'process_flow_id' => $processFlow->id,
            'step_type' => 'automatic',
            'conditions' => [
                [
                    'field' => 'total_amount',
                    'operator' => '>',
                    'value' => 50,
                ],
            ],
        ]);

        // Should execute because order amount (100) > 50
        $result = $this->workflowEngine->executeStep($this->testOrder, $step);
        $this->assertTrue($result);

        // Update condition to fail
        $step->update([
            'conditions' => [
                [
                    'field' => 'total_amount',
                    'operator' => '>',
                    'value' => 200,
                ],
            ],
        ]);

        // Should not execute because order amount (100) < 200
        $result = $this->workflowEngine->executeStep($this->testOrder, $step);
        $this->assertFalse($result);
    }

    public function test_can_assign_task_to_user()
    {
        $processFlow = ProcessFlow::factory()->create();
        $step = WorkflowStep::factory()->create([
            'process_flow_id' => $processFlow->id,
            'step_type' => 'manual',
        ]);

        $taskAssignment = $this->workflowEngine->assignTask($this->testOrder, $step, $this->testUser);

        $this->assertInstanceOf(TaskAssignment::class, $taskAssignment);
        $this->assertEquals($this->testOrder->id, $taskAssignment->order_id);
        $this->assertEquals($step->id, $taskAssignment->workflow_step_id);
        $this->assertEquals($this->testUser->id, $taskAssignment->assigned_to);
        $this->assertEquals('pending', $taskAssignment->status);
        $this->assertNotNull($taskAssignment->assigned_at);
    }

    public function test_can_start_workflow()
    {
        $processFlow = ProcessFlow::factory()->create([
            'conditions' => [
                [
                    'field' => 'platform_type',
                    'operator' => '=',
                    'value' => 'shopee',
                ],
            ],
        ]);

        $step = WorkflowStep::factory()->create([
            'process_flow_id' => $processFlow->id,
            'step_type' => 'automatic',
            'step_order' => 1,
            'auto_execute' => true,
            'configuration' => ['update_workflow_status' => 'processing'],
        ]);

        $result = $this->workflowEngine->startWorkflow($this->testOrder, $processFlow);

        $this->assertTrue($result);
        $this->testOrder->refresh();
        $this->assertEquals('in_progress', $this->testOrder->workflow_status);
    }

    public function test_workflow_fails_when_conditions_not_met()
    {
        $processFlow = ProcessFlow::factory()->create([
            'conditions' => [
                [
                    'field' => 'platform_type',
                    'operator' => '=',
                    'value' => 'lazada', // Order is shopee, so this should fail
                ],
            ],
        ]);

        $result = $this->workflowEngine->startWorkflow($this->testOrder, $processFlow);

        $this->assertFalse($result);
        $this->testOrder->refresh();
        $this->assertEquals('new', $this->testOrder->workflow_status);
    }

    public function test_can_complete_task()
    {
        $taskAssignment = TaskAssignment::factory()->create([
            'order_id' => $this->testOrder->id,
            'assigned_to' => $this->testUser->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $completionData = [
            'notes' => 'Task completed successfully',
            'result' => 'approved',
        ];

        $result = $this->workflowEngine->completeTask($taskAssignment, $completionData);

        $this->assertTrue($result);
        $taskAssignment->refresh();
        $this->assertEquals('completed', $taskAssignment->status);
        $this->assertEquals('Task completed successfully', $taskAssignment->notes);
        $this->assertNotNull($taskAssignment->completed_at);
    }

    public function test_can_get_active_workflows()
    {
        $processFlow = ProcessFlow::factory()->create();
        $step = WorkflowStep::factory()->create([
            'process_flow_id' => $processFlow->id,
        ]);
        
        TaskAssignment::factory()->create([
            'order_id' => $this->testOrder->id,
            'workflow_step_id' => $step->id,
            'status' => 'pending',
        ]);

        $activeWorkflows = $this->workflowEngine->getActiveWorkflows($this->testOrder);

        $this->assertCount(1, $activeWorkflows);
        $this->assertEquals($processFlow->id, $activeWorkflows->first()->id);
    }

    public function test_can_get_pending_tasks()
    {
        $processFlow = ProcessFlow::factory()->create();
        $step = WorkflowStep::factory()->create([
            'process_flow_id' => $processFlow->id,
        ]);
        
        TaskAssignment::factory()->create([
            'order_id' => $this->testOrder->id,
            'workflow_step_id' => $step->id,
            'assigned_to' => $this->testUser->id,
            'status' => 'pending',
        ]);

        $pendingTasks = $this->workflowEngine->getPendingTasks($this->testUser);

        $this->assertCount(1, $pendingTasks);
        $this->assertEquals($this->testUser->id, $pendingTasks->first()->assigned_to);
        $this->assertEquals('pending', $pendingTasks->first()->status);
    }

    public function test_can_pause_and_resume_workflow()
    {
        TaskAssignment::factory()->create([
            'order_id' => $this->testOrder->id,
            'status' => 'pending',
        ]);

        // Pause workflow
        $result = $this->workflowEngine->pauseWorkflow($this->testOrder, 'Testing pause');
        $this->assertTrue($result);
        
        $this->testOrder->refresh();
        $this->assertEquals('on_hold', $this->testOrder->workflow_status);

        // Resume workflow
        $result = $this->workflowEngine->resumeWorkflow($this->testOrder);
        $this->assertTrue($result);
        
        $this->testOrder->refresh();
        $this->assertEquals('in_progress', $this->testOrder->workflow_status);
    }

    public function test_gets_workflow_statistics()
    {
        // Create some test data
        ProcessFlow::factory()->count(3)->create(['is_active' => true]);
        TaskAssignment::factory()->count(5)->create(['status' => 'pending']);
        TaskAssignment::factory()->count(2)->create([
            'status' => 'completed',
            'completed_at' => today(),
        ]);
        Order::factory()->count(4)->create(['workflow_status' => 'in_progress']);

        $stats = $this->workflowEngine->getWorkflowStatistics();

        $this->assertEquals(3, $stats['active_workflows']);
        $this->assertEquals(5, $stats['pending_tasks']);
        $this->assertEquals(2, $stats['completed_tasks_today']);
        $this->assertEquals(4, $stats['orders_in_workflow']);
    }
}