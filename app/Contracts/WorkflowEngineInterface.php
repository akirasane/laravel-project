<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\ProcessFlow;
use App\Models\WorkflowStep;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

interface WorkflowEngineInterface
{
    /**
     * Create a new process flow with workflow steps.
     */
    public function createFlow(string $name, string $description, array $steps, array $conditions = []): ProcessFlow;

    /**
     * Execute a workflow step for an order.
     */
    public function executeStep(Order $order, WorkflowStep $step): bool;

    /**
     * Assign a task to a user for a workflow step.
     */
    public function assignTask(Order $order, WorkflowStep $step, User $user = null): TaskAssignment;

    /**
     * Evaluate conditions for workflow branching.
     */
    public function evaluateConditions(Order $order, array $conditions): bool;

    /**
     * Start workflow for an order.
     */
    public function startWorkflow(Order $order, ProcessFlow $processFlow): bool;

    /**
     * Complete a task assignment.
     */
    public function completeTask(TaskAssignment $taskAssignment, array $completionData = []): bool;

    /**
     * Get active workflows for an order.
     */
    public function getActiveWorkflows(Order $order): Collection;

    /**
     * Get pending tasks for a user.
     */
    public function getPendingTasks(User $user): Collection;

    /**
     * Get workflow statistics.
     */
    public function getWorkflowStatistics(): array;

    /**
     * Pause workflow for an order.
     */
    public function pauseWorkflow(Order $order, string $reason = null): bool;

    /**
     * Resume workflow for an order.
     */
    public function resumeWorkflow(Order $order): bool;
}