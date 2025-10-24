<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TaskManager;
use App\Models\Order;
use App\Models\ProcessFlow;
use App\Models\WorkflowStep;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class TaskManagerTest extends TestCase
{
    use RefreshDatabase;

    protected TaskManager $taskManager;
    protected User $testUser;
    protected Order $testOrder;
    protected WorkflowStep $testStep;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->taskManager = new TaskManager();
        
        $this->testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        
        $this->testOrder = Order::factory()->create([
            'platform_order_id' => 'TEST-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'currency' => 'USD',
        ]);

        $processFlow = ProcessFlow::factory()->create();
        $this->testStep = WorkflowStep::factory()->create([
            'process_flow_id' => $processFlow->id,
            'name' => 'Test Step',
            'step_type' => 'manual',
        ]);

        Notification::fake();
    }

    public function test_can_assign_task_to_user()
    {
        $taskData = ['custom_field' => 'custom_value'];

        $taskAssignment = $this->taskManager->assignTask(
            $this->testOrder,
            $this->testStep,
            $this->testUser,
            $taskData
        );

        $this->assertInstanceOf(TaskAssignment::class, $taskAssignment);
        $this->assertEquals($this->testOrder->id, $taskAssignment->order_id);
        $this->assertEquals($this->testStep->id, $taskAssignment->workflow_step_id);
        $this->assertEquals($this->testUser->id, $taskAssignment->assigned_to);
        $this->assertEquals('pending', $taskAssignment->status);
        $this->assertNotNull($taskAssignment->assigned_at);
        
        // Check task data
        $this->assertArrayHasKey('custom_field', $taskAssignment->task_data);
        $this->assertEquals('custom_value', $taskAssignment->task_data['custom_field']);
        $this->assertArrayHasKey('order_summary', $taskAssignment->task_data);
    }

    public function test_can_start_task()
    {
        $taskAssignment = TaskAssignment::factory()->create([
            'order_id' => $this->testOrder->id,
            'workflow_step_id' => $this->testStep->id,
            'assigned_to' => $this->testUser->id,
            'status' => 'pending',
        ]);

        $result = $this->taskManager->startTask($taskAssignment, $this->testUser);

        $this->assertTrue($result);
        $taskAssignment->refresh();
        $this->assertEquals('in_progress', $taskAssignment->status);
        $this->assertNotNull($taskAssignment->started_at);
    }

    public function test_cannot_start_task_assigned_to_different_user()
    {
        $otherUser = User::factory()->create();
        $taskAssignment = TaskAssignment::factory()->create([
            'assigned_to' => $otherUser->id,
            'status' => 'pending',
        ]);

        $result = $this->taskManager->startTask($taskAssignment, $this->testUser);

        $this->assertFalse($result);
        $taskAssignment->refresh();
        $this->assertEquals('pending', $taskAssignment->status);
    }

    public function test_can_complete_task()
    {
        $taskAssignment = TaskAssignment::factory()->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(30),
        ]);

        $completionData = [
            'notes' => 'Task completed successfully',
            'result' => 'approved',
        ];

        $result = $this->taskManager->completeTask($taskAssignment, $completionData, $this->testUser);

        $this->assertTrue($result);
        $taskAssignment->refresh();
        $this->assertEquals('completed', $taskAssignment->status);
        $this->assertEquals('Task completed successfully', $taskAssignment->notes);
        $this->assertNotNull($taskAssignment->completed_at);
        $this->assertArrayHasKey('completion_data', $taskAssignment->task_data);
    }

    public function test_can_pause_task()
    {
        $taskAssignment = TaskAssignment::factory()->create([
            'status' => 'in_progress',
        ]);

        $result = $this->taskManager->pauseTask($taskAssignment, 'Waiting for customer response');

        $this->assertTrue($result);
        $taskAssignment->refresh();
        $this->assertEquals('on_hold', $taskAssignment->status);
        $this->assertArrayHasKey('pause_reason', $taskAssignment->task_data);
        $this->assertEquals('Waiting for customer response', $taskAssignment->task_data['pause_reason']);
    }

    public function test_can_resume_task()
    {
        $taskAssignment = TaskAssignment::factory()->create([
            'status' => 'on_hold',
            'started_at' => now()->subHour(),
        ]);

        $result = $this->taskManager->resumeTask($taskAssignment);

        $this->assertTrue($result);
        $taskAssignment->refresh();
        $this->assertEquals('in_progress', $taskAssignment->status);
        $this->assertArrayHasKey('resumed_at', $taskAssignment->task_data);
    }

    public function test_can_cancel_task()
    {
        $taskAssignment = TaskAssignment::factory()->create([
            'status' => 'pending',
        ]);

        $result = $this->taskManager->cancelTask($taskAssignment, 'Order cancelled by customer');

        $this->assertTrue($result);
        $taskAssignment->refresh();
        $this->assertEquals('cancelled', $taskAssignment->status);
        $this->assertArrayHasKey('cancellation_reason', $taskAssignment->task_data);
        $this->assertEquals('Order cancelled by customer', $taskAssignment->task_data['cancellation_reason']);
    }

    public function test_can_reassign_task()
    {
        $newUser = User::factory()->create();
        $taskAssignment = TaskAssignment::factory()->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'pending',
        ]);

        $result = $this->taskManager->reassignTask($taskAssignment, $newUser, 'User unavailable');

        $this->assertTrue($result);
        $taskAssignment->refresh();
        $this->assertEquals($newUser->id, $taskAssignment->assigned_to);
        $this->assertArrayHasKey('reassignment_history', $taskAssignment->task_data);
        
        $history = $taskAssignment->task_data['reassignment_history'];
        $this->assertCount(1, $history);
        $this->assertEquals($this->testUser->id, $history[0]['from_user_id']);
        $this->assertEquals($newUser->id, $history[0]['to_user_id']);
        $this->assertEquals('User unavailable', $history[0]['reason']);
    }

    public function test_gets_pending_tasks_for_user()
    {
        // Create pending tasks for the user
        TaskAssignment::factory()->count(3)->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'pending',
        ]);

        // Create tasks for other users
        TaskAssignment::factory()->count(2)->create([
            'assigned_to' => User::factory()->create()->id,
            'status' => 'pending',
        ]);

        // Create completed tasks for the user
        TaskAssignment::factory()->count(1)->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'completed',
        ]);

        $pendingTasks = $this->taskManager->getPendingTasks($this->testUser);

        $this->assertCount(3, $pendingTasks);
        foreach ($pendingTasks as $task) {
            $this->assertEquals($this->testUser->id, $task->assigned_to);
            $this->assertEquals('pending', $task->status);
        }
    }

    public function test_gets_in_progress_tasks_for_user()
    {
        TaskAssignment::factory()->count(2)->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'in_progress',
        ]);

        $inProgressTasks = $this->taskManager->getInProgressTasks($this->testUser);

        $this->assertCount(2, $inProgressTasks);
        foreach ($inProgressTasks as $task) {
            $this->assertEquals($this->testUser->id, $task->assigned_to);
            $this->assertEquals('in_progress', $task->status);
        }
    }

    public function test_gets_user_task_statistics()
    {
        // Create various tasks for the user
        TaskAssignment::factory()->count(3)->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'pending',
        ]);

        TaskAssignment::factory()->count(2)->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'in_progress',
        ]);

        TaskAssignment::factory()->count(1)->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'completed',
            'completed_at' => today(),
        ]);

        TaskAssignment::factory()->count(1)->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'on_hold',
        ]);

        // Create overdue task
        TaskAssignment::factory()->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'pending',
            'assigned_at' => now()->subHours(25),
        ]);

        $stats = $this->taskManager->getUserTaskStatistics($this->testUser);

        $this->assertEquals(4, $stats['pending']); // 3 + 1 overdue
        $this->assertEquals(2, $stats['in_progress']);
        $this->assertEquals(1, $stats['completed_today']);
        $this->assertEquals(1, $stats['on_hold']);
        $this->assertEquals(1, $stats['overdue']);
    }

    public function test_gets_overall_task_statistics()
    {
        // Create tasks with different statuses
        TaskAssignment::factory()->count(5)->create(['status' => 'pending']);
        TaskAssignment::factory()->count(3)->create(['status' => 'in_progress']);
        TaskAssignment::factory()->count(2)->create([
            'status' => 'completed',
            'completed_at' => today(),
        ]);
        TaskAssignment::factory()->count(1)->create(['status' => 'on_hold']);
        TaskAssignment::factory()->create([
            'status' => 'pending',
            'assigned_at' => now()->subHours(25),
        ]);

        $stats = $this->taskManager->getOverallTaskStatistics();

        $this->assertEquals(6, $stats['total_pending']); // 5 + 1 overdue
        $this->assertEquals(3, $stats['total_in_progress']);
        $this->assertEquals(2, $stats['total_completed_today']);
        $this->assertEquals(1, $stats['total_on_hold']);
        $this->assertEquals(1, $stats['total_overdue']);
    }

    public function test_gets_tasks_by_status()
    {
        TaskAssignment::factory()->count(3)->create(['status' => 'pending']);
        TaskAssignment::factory()->count(2)->create(['status' => 'completed']);

        $pendingTasks = $this->taskManager->getTasksByStatus('pending');
        $completedTasks = $this->taskManager->getTasksByStatus('completed');

        $this->assertCount(3, $pendingTasks);
        $this->assertCount(2, $completedTasks);
    }

    public function test_gets_overdue_tasks()
    {
        // Create overdue tasks
        TaskAssignment::factory()->count(2)->create([
            'status' => 'pending',
            'assigned_at' => now()->subHours(25),
        ]);

        // Create non-overdue tasks
        TaskAssignment::factory()->count(3)->create([
            'status' => 'pending',
            'assigned_at' => now()->subHours(12),
        ]);

        $overdueTasks = $this->taskManager->getOverdueTasks(24);

        $this->assertCount(2, $overdueTasks);
        foreach ($overdueTasks as $task) {
            $this->assertTrue($task->assigned_at->lt(now()->subHours(24)));
        }
    }

    public function test_gets_task_performance_metrics()
    {
        // Create completed tasks with different completion times
        TaskAssignment::factory()->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'completed',
            'started_at' => now()->subDays(5)->subMinutes(60),
            'completed_at' => now()->subDays(5),
        ]);

        TaskAssignment::factory()->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'completed',
            'started_at' => now()->subDays(3)->subMinutes(30),
            'completed_at' => now()->subDays(3),
        ]);

        // Create assigned but not completed task
        TaskAssignment::factory()->create([
            'assigned_to' => $this->testUser->id,
            'status' => 'pending',
            'assigned_at' => now()->subDays(2),
        ]);

        $metrics = $this->taskManager->getTaskPerformanceMetrics($this->testUser, 30);

        $this->assertEquals(2, $metrics['total_completed']);
        $this->assertEquals(45, $metrics['average_completion_time_minutes']); // (60 + 30) / 2
        $this->assertEquals(66.67, $metrics['completion_rate']); // 2 completed out of 3 assigned
        $this->assertEquals(0.07, $metrics['tasks_per_day']); // 2 tasks / 30 days
    }
}