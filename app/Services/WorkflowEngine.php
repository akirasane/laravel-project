<?php

namespace App\Services;

use App\Contracts\WorkflowEngineInterface;
use App\Models\Order;
use App\Models\ProcessFlow;
use App\Models\WorkflowStep;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\WorkflowConditionEvaluator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WorkflowEngine implements WorkflowEngineInterface
{
    protected WorkflowConditionEvaluator $conditionEvaluator;

    public function __construct(WorkflowConditionEvaluator $conditionEvaluator)
    {
        $this->conditionEvaluator = $conditionEvaluator;
    }
    /**
     * Create a new process flow with workflow steps.
     */
    public function createFlow(string $name, string $description, array $steps, array $conditions = []): ProcessFlow
    {
        return DB::transaction(function () use ($name, $description, $steps, $conditions) {
            $processFlow = ProcessFlow::create([
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'conditions' => $conditions,
                'created_by' => auth()->id(),
            ]);

            foreach ($steps as $index => $stepData) {
                WorkflowStep::create([
                    'process_flow_id' => $processFlow->id,
                    'name' => $stepData['name'],
                    'step_order' => $index + 1,
                    'step_type' => $stepData['type'],
                    'assigned_role' => $stepData['assigned_role'] ?? null,
                    'auto_execute' => $stepData['auto_execute'] ?? false,
                    'conditions' => $stepData['conditions'] ?? [],
                    'configuration' => $stepData['configuration'] ?? [],
                ]);
            }

            return $processFlow->load('workflowSteps');
        });
    }

    /**
     * Execute a workflow step for an order.
     */
    public function executeStep(Order $order, WorkflowStep $step): bool
    {
        try {
            Log::info("Executing workflow step", [
                'order_id' => $order->id,
                'step_id' => $step->id,
                'step_name' => $step->name,
                'step_type' => $step->step_type
            ]);

            // Check if step conditions are met
            if (!$this->evaluateConditions($order, $step->conditions ?? [])) {
                Log::info("Step conditions not met, skipping", [
                    'order_id' => $order->id,
                    'step_id' => $step->id
                ]);
                return false;
            }     
       // Execute step based on type
            $result = match ($step->step_type) {
                'automatic' => $this->executeAutomaticStep($order, $step),
                'manual' => $this->executeManualStep($order, $step),
                'approval' => $this->executeApprovalStep($order, $step),
                'notification' => $this->executeNotificationStep($order, $step),
                'billing' => $this->executeBillingStep($order, $step),
                'packing' => $this->executePackingStep($order, $step),
                'return' => $this->executeReturnStep($order, $step),
                default => throw new \InvalidArgumentException("Unknown step type: {$step->step_type}")
            };

            if ($result) {
                $this->logStepExecution($order, $step, 'completed');
                $this->advanceToNextStep($order, $step);
            } else {
                $this->logStepExecution($order, $step, 'failed');
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Workflow step execution failed", [
                'order_id' => $order->id,
                'step_id' => $step->id,
                'error' => $e->getMessage()
            ]);
            $this->logStepExecution($order, $step, 'error', $e->getMessage());
            return false;
        }
    }

    /**
     * Assign a task to a user for a workflow step.
     */
    public function assignTask(Order $order, WorkflowStep $step, User $user = null): TaskAssignment
    {
        // If no user specified, find available user with the required role
        if (!$user && $step->assigned_role) {
            $user = $this->findAvailableUser($step->assigned_role);
        }

        $taskAssignment = TaskAssignment::create([
            'order_id' => $order->id,
            'workflow_step_id' => $step->id,
            'assigned_to' => $user?->id,
            'status' => 'pending',
            'assigned_at' => now(),
            'task_data' => [
                'step_configuration' => $step->configuration,
                'order_data' => $order->toArray(),
            ],
        ]);

        Log::info("Task assigned", [
            'task_id' => $taskAssignment->id,
            'order_id' => $order->id,
            'step_id' => $step->id,
            'assigned_to' => $user?->id
        ]);

        return $taskAssignment;
    }  
  /**
     * Evaluate conditions for workflow branching.
     */
    public function evaluateConditions(Order $order, array $conditions): bool
    {
        return $this->conditionEvaluator->evaluate($order, $conditions);
    }

    /**
     * Start workflow for an order.
     */
    public function startWorkflow(Order $order, ProcessFlow $processFlow): bool
    {
        try {
            // Check if process flow conditions are met
            if (!$this->evaluateConditions($order, $processFlow->conditions ?? [])) {
                Log::info("Process flow conditions not met", [
                    'order_id' => $order->id,
                    'process_flow_id' => $processFlow->id
                ]);
                return false;
            }

            // Update order workflow status
            $order->update(['workflow_status' => 'in_progress']);

            // Get first step and execute it
            $firstStep = $processFlow->workflowSteps()->orderBy('step_order')->first();
            if ($firstStep) {
                if ($firstStep->auto_execute) {
                    return $this->executeStep($order, $firstStep);
                } else {
                    $this->assignTask($order, $firstStep);
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Failed to start workflow", [
                'order_id' => $order->id,
                'process_flow_id' => $processFlow->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Complete a task assignment.
     */
    public function completeTask(TaskAssignment $taskAssignment, array $completionData = []): bool
    {
        try {
            $taskAssignment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'notes' => $completionData['notes'] ?? null,
                'task_data' => array_merge($taskAssignment->task_data ?? [], $completionData),
            ]);

            Log::info("Task completed", [
                'task_id' => $taskAssignment->id,
                'order_id' => $taskAssignment->order_id,
                'step_id' => $taskAssignment->workflow_step_id
            ]);

            // Advance to next step
            $this->advanceToNextStep($taskAssignment->order, $taskAssignment->workflowStep);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to complete task", [
                'task_id' => $taskAssignment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }    
/**
     * Get active workflows for an order.
     */
    public function getActiveWorkflows(Order $order): Collection
    {
        return ProcessFlow::active()
            ->whereHas('workflowSteps.taskAssignments', function ($query) use ($order) {
                $query->where('order_id', $order->id)
                      ->whereIn('status', ['pending', 'in_progress']);
            })
            ->with(['workflowSteps.taskAssignments' => function ($query) use ($order) {
                $query->where('order_id', $order->id);
            }])
            ->get();
    }

    /**
     * Get pending tasks for a user.
     */
    public function getPendingTasks(User $user): Collection
    {
        return TaskAssignment::pending()
            ->assignedTo($user->id)
            ->with(['order', 'workflowStep.processFlow'])
            ->orderBy('assigned_at')
            ->get();
    }

    /**
     * Execute automatic step.
     */
    private function executeAutomaticStep(Order $order, WorkflowStep $step): bool
    {
        // Automatic steps execute immediately based on configuration
        $config = $step->configuration ?? [];
        
        // Example: Update order status
        if (isset($config['update_status'])) {
            $order->update(['status' => $config['update_status']]);
        }

        // Example: Update workflow status
        if (isset($config['update_workflow_status'])) {
            $order->update(['workflow_status' => $config['update_workflow_status']]);
        }

        return true;
    }

    /**
     * Execute manual step.
     */
    private function executeManualStep(Order $order, WorkflowStep $step): bool
    {
        // Manual steps require task assignment
        $this->assignTask($order, $step);
        return true;
    }

    /**
     * Execute approval step.
     */
    private function executeApprovalStep(Order $order, WorkflowStep $step): bool
    {
        // Approval steps require task assignment to approver
        $this->assignTask($order, $step);
        return true;
    }

    /**
     * Execute notification step.
     */
    private function executeNotificationStep(Order $order, WorkflowStep $step): bool
    {
        // Send notifications based on configuration
        $config = $step->configuration ?? [];
        
        // This would integrate with notification service
        Log::info("Notification step executed", [
            'order_id' => $order->id,
            'notification_type' => $config['type'] ?? 'general'
        ]);

        return true;
    }    /**
 
    * Execute billing step.
     */
    private function executeBillingStep(Order $order, WorkflowStep $step): bool
    {
        // Billing steps require task assignment to billing clerk
        $this->assignTask($order, $step);
        return true;
    }

    /**
     * Execute packing step.
     */
    private function executePackingStep(Order $order, WorkflowStep $step): bool
    {
        // Packing steps require task assignment to warehouse staff
        $this->assignTask($order, $step);
        return true;
    }

    /**
     * Execute return step.
     */
    private function executeReturnStep(Order $order, WorkflowStep $step): bool
    {
        // Return steps require task assignment to customer service
        $this->assignTask($order, $step);
        return true;
    }



    /**
     * Find available user with required role.
     */
    private function findAvailableUser(string $role): ?User
    {
        // This would integrate with role/permission system
        // For now, return first user with the role
        return User::whereHas('roles', function ($query) use ($role) {
            $query->where('name', $role);
        })->first();
    }

    /**
     * Advance to next step in workflow.
     */
    private function advanceToNextStep(Order $order, WorkflowStep $currentStep): void
    {
        $nextStep = WorkflowStep::where('process_flow_id', $currentStep->process_flow_id)
            ->where('step_order', '>', $currentStep->step_order)
            ->orderBy('step_order')
            ->first();

        if ($nextStep) {
            if ($nextStep->auto_execute) {
                $this->executeStep($order, $nextStep);
            } else {
                $this->assignTask($order, $nextStep);
            }
        } else {
            // Workflow completed
            $order->update(['workflow_status' => 'completed']);
            Log::info("Workflow completed", ['order_id' => $order->id]);
        }
    }    /**

     * Log step execution.
     */
    private function logStepExecution(Order $order, WorkflowStep $step, string $status, string $notes = null): void
    {
        // This would create an audit trail entry
        Log::info("Workflow step execution logged", [
            'order_id' => $order->id,
            'step_id' => $step->id,
            'step_name' => $step->name,
            'status' => $status,
            'notes' => $notes,
            'timestamp' => now()
        ]);
    }

    /**
     * Get workflow statistics.
     */
    public function getWorkflowStatistics(): array
    {
        return [
            'active_workflows' => ProcessFlow::active()->count(),
            'pending_tasks' => TaskAssignment::pending()->count(),
            'completed_tasks_today' => TaskAssignment::where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
            'orders_in_workflow' => Order::where('workflow_status', 'in_progress')->count(),
        ];
    }

    /**
     * Pause workflow for an order.
     */
    public function pauseWorkflow(Order $order, string $reason = null): bool
    {
        try {
            $order->update(['workflow_status' => 'on_hold']);
            
            // Pause all pending tasks
            TaskAssignment::where('order_id', $order->id)
                ->where('status', 'pending')
                ->update(['status' => 'on_hold']);

            Log::info("Workflow paused", [
                'order_id' => $order->id,
                'reason' => $reason
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to pause workflow", [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Resume workflow for an order.
     */
    public function resumeWorkflow(Order $order): bool
    {
        try {
            $order->update(['workflow_status' => 'in_progress']);
            
            // Resume paused tasks
            TaskAssignment::where('order_id', $order->id)
                ->where('status', 'on_hold')
                ->update(['status' => 'pending']);

            Log::info("Workflow resumed", ['order_id' => $order->id]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to resume workflow", [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}