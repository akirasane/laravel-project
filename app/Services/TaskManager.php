<?php

namespace App\Services;

use App\Models\TaskAssignment;
use App\Models\User;
use App\Models\Order;
use App\Models\WorkflowStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TaskAssignedNotification;

class TaskManager
{
    /**
     * Assign a task to a user.
     */
    public function assignTask(
        Order $order,
        WorkflowStep $step,
        User $user = null,
        array $taskData = []
    ): TaskAssignment {
        return DB::transaction(function () use ($order, $step, $user, $taskData) {
            // If no user specified, find available user with required role
            if (!$user && $step->assigned_role) {
                $user = $this->findAvailableUser($step->assigned_role);
            }

            $taskAssignment = TaskAssignment::create([
                'order_id' => $order->id,
                'workflow_step_id' => $step->id,
                'assigned_to' => $user?->id,
                'status' => 'pending',
                'assigned_at' => now(),
                'task_data' => array_merge([
                    'step_configuration' => $step->configuration,
                    'order_summary' => [
                        'platform_order_id' => $order->platform_order_id,
                        'customer_name' => $order->customer_name,
                        'total_amount' => $order->total_amount,
                        'currency' => $order->currency,
                    ],
                ], $taskData),
            ]);

            // Send notification to assigned user
            if ($user) {
                $this->notifyUserOfTaskAssignment($user, $taskAssignment);
            }

            Log::info("Task assigned", [
                'task_id' => $taskAssignment->id,
                'order_id' => $order->id,
                'step_id' => $step->id,
                'assigned_to' => $user?->id,
                'step_name' => $step->name,
            ]);

            return $taskAssignment;
        });
    }

    /**
     * Start a task.
     */
    public function startTask(TaskAssignment $taskAssignment, User $user = null): bool
    {
        try {
            // Verify user can start this task
            if ($user && $taskAssignment->assigned_to && $taskAssignment->assigned_to !== $user->id) {
                throw new \Exception('User not authorized to start this task');
            }

            $taskAssignment->update([
                'status' => 'in_progress',
                'started_at' => now(),
                'assigned_to' => $user?->id ?? $taskAssignment->assigned_to,
            ]);

            Log::info("Task started", [
                'task_id' => $taskAssignment->id,
                'user_id' => $user?->id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to start task", [
                'task_id' => $taskAssignment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Complete a task.
     */
    public function completeTask(
        TaskAssignment $taskAssignment,
        array $completionData = [],
        User $user = null
    ): bool {
        try {
            // Verify user can complete this task
            if ($user && $taskAssignment->assigned_to && $taskAssignment->assigned_to !== $user->id) {
                throw new \Exception('User not authorized to complete this task');
            }

            $taskAssignment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'notes' => $completionData['notes'] ?? $taskAssignment->notes,
                'task_data' => array_merge($taskAssignment->task_data ?? [], [
                    'completion_data' => $completionData,
                    'completed_by' => $user?->id,
                ]),
            ]);

            Log::info("Task completed", [
                'task_id' => $taskAssignment->id,
                'user_id' => $user?->id,
                'completion_data' => $completionData,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to complete task", [
                'task_id' => $taskAssignment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }    /**
  
   * Pause a task.
     */
    public function pauseTask(TaskAssignment $taskAssignment, string $reason = null): bool
    {
        try {
            $taskAssignment->update([
                'status' => 'on_hold',
                'task_data' => array_merge($taskAssignment->task_data ?? [], [
                    'pause_reason' => $reason,
                    'paused_at' => now(),
                ]),
            ]);

            Log::info("Task paused", [
                'task_id' => $taskAssignment->id,
                'reason' => $reason,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to pause task", [
                'task_id' => $taskAssignment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Resume a paused task.
     */
    public function resumeTask(TaskAssignment $taskAssignment): bool
    {
        try {
            $previousStatus = $taskAssignment->started_at ? 'in_progress' : 'pending';
            
            $taskAssignment->update([
                'status' => $previousStatus,
                'task_data' => array_merge($taskAssignment->task_data ?? [], [
                    'resumed_at' => now(),
                ]),
            ]);

            Log::info("Task resumed", [
                'task_id' => $taskAssignment->id,
                'status' => $previousStatus,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to resume task", [
                'task_id' => $taskAssignment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Cancel a task.
     */
    public function cancelTask(TaskAssignment $taskAssignment, string $reason = null): bool
    {
        try {
            $taskAssignment->update([
                'status' => 'cancelled',
                'task_data' => array_merge($taskAssignment->task_data ?? [], [
                    'cancellation_reason' => $reason,
                    'cancelled_at' => now(),
                ]),
            ]);

            Log::info("Task cancelled", [
                'task_id' => $taskAssignment->id,
                'reason' => $reason,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to cancel task", [
                'task_id' => $taskAssignment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Reassign a task to another user.
     */
    public function reassignTask(TaskAssignment $taskAssignment, User $newUser, string $reason = null): bool
    {
        try {
            $oldUserId = $taskAssignment->assigned_to;
            
            $taskAssignment->update([
                'assigned_to' => $newUser->id,
                'task_data' => array_merge($taskAssignment->task_data ?? [], [
                    'reassignment_history' => array_merge(
                        $taskAssignment->task_data['reassignment_history'] ?? [],
                        [[
                            'from_user_id' => $oldUserId,
                            'to_user_id' => $newUser->id,
                            'reason' => $reason,
                            'reassigned_at' => now(),
                        ]]
                    ),
                ]),
            ]);

            // Notify new user
            $this->notifyUserOfTaskAssignment($newUser, $taskAssignment);

            Log::info("Task reassigned", [
                'task_id' => $taskAssignment->id,
                'from_user_id' => $oldUserId,
                'to_user_id' => $newUser->id,
                'reason' => $reason,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to reassign task", [
                'task_id' => $taskAssignment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }    /*
*
     * Get pending tasks for a user.
     */
    public function getPendingTasks(User $user, int $limit = null): Collection
    {
        $query = TaskAssignment::pending()
            ->assignedTo($user->id)
            ->with(['order', 'workflowStep.processFlow'])
            ->orderBy('assigned_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get in-progress tasks for a user.
     */
    public function getInProgressTasks(User $user, int $limit = null): Collection
    {
        $query = TaskAssignment::where('status', 'in_progress')
            ->assignedTo($user->id)
            ->with(['order', 'workflowStep.processFlow'])
            ->orderBy('started_at');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get task statistics for a user.
     */
    public function getUserTaskStatistics(User $user): array
    {
        $baseQuery = TaskAssignment::assignedTo($user->id);

        return [
            'pending' => (clone $baseQuery)->pending()->count(),
            'in_progress' => (clone $baseQuery)->where('status', 'in_progress')->count(),
            'completed_today' => (clone $baseQuery)
                ->where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
            'completed_this_week' => (clone $baseQuery)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'on_hold' => (clone $baseQuery)->where('status', 'on_hold')->count(),
            'overdue' => (clone $baseQuery)
                ->pending()
                ->where('assigned_at', '<', now()->subHours(24))
                ->count(),
        ];
    }

    /**
     * Get overall task statistics.
     */
    public function getOverallTaskStatistics(): array
    {
        return [
            'total_pending' => TaskAssignment::pending()->count(),
            'total_in_progress' => TaskAssignment::where('status', 'in_progress')->count(),
            'total_completed_today' => TaskAssignment::where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
            'total_on_hold' => TaskAssignment::where('status', 'on_hold')->count(),
            'total_overdue' => TaskAssignment::pending()
                ->where('assigned_at', '<', now()->subHours(24))
                ->count(),
            'average_completion_time' => $this->getAverageCompletionTime(),
        ];
    }

    /**
     * Get tasks by status.
     */
    public function getTasksByStatus(string $status, int $limit = null): Collection
    {
        $query = TaskAssignment::byStatus($status)
            ->with(['order', 'workflowStep.processFlow', 'assignedUser'])
            ->orderBy('assigned_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get overdue tasks.
     */
    public function getOverdueTasks(int $hoursOverdue = 24): Collection
    {
        return TaskAssignment::pending()
            ->where('assigned_at', '<', now()->subHours($hoursOverdue))
            ->with(['order', 'workflowStep.processFlow', 'assignedUser'])
            ->orderBy('assigned_at')
            ->get();
    }    /**

     * Find available user with required role.
     */
    private function findAvailableUser(string $role): ?User
    {
        // Get users with the required role, ordered by current task load
        return User::whereHas('roles', function ($query) use ($role) {
            $query->where('name', $role);
        })
        ->withCount(['taskAssignments as pending_tasks_count' => function ($query) {
            $query->whereIn('status', ['pending', 'in_progress']);
        }])
        ->orderBy('pending_tasks_count')
        ->first();
    }

    /**
     * Notify user of task assignment.
     */
    private function notifyUserOfTaskAssignment(User $user, TaskAssignment $taskAssignment): void
    {
        try {
            $user->notify(new TaskAssignedNotification($taskAssignment));

            Log::info("Task assignment notification sent", [
                'user_id' => $user->id,
                'task_id' => $taskAssignment->id,
                'step_name' => $taskAssignment->workflowStep->name,
                'order_id' => $taskAssignment->order->platform_order_id,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send task assignment notification", [
                'user_id' => $user->id,
                'task_id' => $taskAssignment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get average completion time for tasks.
     */
    private function getAverageCompletionTime(): ?float
    {
        $completedTasks = TaskAssignment::where('status', 'completed')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as avg_minutes')
            ->first();

        return $completedTasks?->avg_minutes;
    }

    /**
     * Get task performance metrics.
     */
    public function getTaskPerformanceMetrics(User $user = null, int $days = 30): array
    {
        $query = TaskAssignment::where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays($days));

        if ($user) {
            $query->assignedTo($user->id);
        }

        $tasks = $query->get();

        if ($tasks->isEmpty()) {
            return [
                'total_completed' => 0,
                'average_completion_time_minutes' => 0,
                'completion_rate' => 0,
                'tasks_per_day' => 0,
            ];
        }

        $totalTasks = $tasks->count();
        $completionTimes = $tasks->filter(function ($task) {
            return $task->started_at && $task->completed_at;
        })->map(function ($task) {
            return $task->started_at->diffInMinutes($task->completed_at);
        });

        $averageCompletionTime = $completionTimes->avg();

        // Calculate completion rate (completed vs assigned)
        $assignedQuery = TaskAssignment::where('assigned_at', '>=', now()->subDays($days));
        if ($user) {
            $assignedQuery->assignedTo($user->id);
        }
        $totalAssigned = $assignedQuery->count();
        $completionRate = $totalAssigned > 0 ? ($totalTasks / $totalAssigned) * 100 : 0;

        return [
            'total_completed' => $totalTasks,
            'average_completion_time_minutes' => round($averageCompletionTime, 2),
            'completion_rate' => round($completionRate, 2),
            'tasks_per_day' => round($totalTasks / $days, 2),
        ];
    }
}