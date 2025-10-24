<?php

namespace App\Services;

use App\Models\TaskAssignment;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaskReportingService
{
    /**
     * Generate task completion report.
     */
    public function generateCompletionReport(
        Carbon $startDate,
        Carbon $endDate,
        User $user = null
    ): array {
        $query = TaskAssignment::where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->with(['workflowStep.processFlow', 'assignedUser', 'order']);

        if ($user) {
            $query->assignedTo($user->id);
        }

        $tasks = $query->get();

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'user' => $user ? $user->name : 'All Users',
            'total_completed' => $tasks->count(),
            'completion_by_type' => $this->groupTasksByType($tasks),
            'completion_by_workflow' => $this->groupTasksByWorkflow($tasks),
            'completion_by_user' => $user ? [] : $this->groupTasksByUser($tasks),
            'average_completion_time' => $this->calculateAverageCompletionTime($tasks),
            'daily_completion_trend' => $this->getDailyCompletionTrend($tasks, $startDate, $endDate),
        ];
    }

    /**
     * Generate task performance report.
     */
    public function generatePerformanceReport(
        Carbon $startDate,
        Carbon $endDate,
        User $user = null
    ): array {
        $baseQuery = TaskAssignment::whereBetween('assigned_at', [$startDate, $endDate]);

        if ($user) {
            $baseQuery->assignedTo($user->id);
        }

        $totalAssigned = (clone $baseQuery)->count();
        $completed = (clone $baseQuery)->where('status', 'completed')->count();
        $pending = (clone $baseQuery)->where('status', 'pending')->count();
        $inProgress = (clone $baseQuery)->where('status', 'in_progress')->count();
        $overdue = (clone $baseQuery)
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('assigned_at', '<', now()->subHours(24))
            ->count();

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'user' => $user ? $user->name : 'All Users',
            'total_assigned' => $totalAssigned,
            'completed' => $completed,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'overdue' => $overdue,
            'completion_rate' => $totalAssigned > 0 ? round(($completed / $totalAssigned) * 100, 2) : 0,
            'overdue_rate' => $totalAssigned > 0 ? round(($overdue / $totalAssigned) * 100, 2) : 0,
        ];
    }

    /**
     * Generate workflow efficiency report.
     */
    public function generateWorkflowEfficiencyReport(
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $tasks = TaskAssignment::where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->whereNotNull('started_at')
            ->with(['workflowStep.processFlow'])
            ->get();

        $workflowStats = $tasks->groupBy('workflowStep.processFlow.name')
            ->map(function ($workflowTasks, $workflowName) {
                $completionTimes = $workflowTasks->map(function ($task) {
                    return $task->started_at->diffInMinutes($task->completed_at);
                });

                return [
                    'workflow_name' => $workflowName,
                    'total_tasks' => $workflowTasks->count(),
                    'average_completion_time' => round($completionTimes->avg(), 2),
                    'min_completion_time' => $completionTimes->min(),
                    'max_completion_time' => $completionTimes->max(),
                    'step_breakdown' => $this->getStepBreakdown($workflowTasks),
                ];
            })
            ->values()
            ->toArray();

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'workflow_statistics' => $workflowStats,
        ];
    }

    /**
     * Get overdue tasks report.
     */
    public function getOverdueTasksReport(int $hoursOverdue = 24): array
    {
        $overdueTasks = TaskAssignment::whereIn('status', ['pending', 'in_progress'])
            ->where('assigned_at', '<', now()->subHours($hoursOverdue))
            ->with(['workflowStep.processFlow', 'assignedUser', 'order'])
            ->orderBy('assigned_at')
            ->get();

        return [
            'total_overdue' => $overdueTasks->count(),
            'hours_threshold' => $hoursOverdue,
            'overdue_by_user' => $this->groupTasksByUser($overdueTasks),
            'overdue_by_type' => $this->groupTasksByType($overdueTasks),
            'overdue_by_workflow' => $this->groupTasksByWorkflow($overdueTasks),
            'oldest_task' => $overdueTasks->first() ? [
                'id' => $overdueTasks->first()->id,
                'assigned_at' => $overdueTasks->first()->assigned_at,
                'hours_overdue' => $overdueTasks->first()->assigned_at->diffInHours(now()),
                'step_name' => $overdueTasks->first()->workflowStep->name,
                'order_id' => $overdueTasks->first()->order->platform_order_id,
            ] : null,
        ];
    }    /**

     * Group tasks by type.
     */
    private function groupTasksByType(Collection $tasks): array
    {
        return $tasks->groupBy('workflowStep.step_type')
            ->map(function ($typeTasks, $type) {
                return [
                    'type' => $type,
                    'count' => $typeTasks->count(),
                    'percentage' => 0, // Will be calculated later
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Group tasks by workflow.
     */
    private function groupTasksByWorkflow(Collection $tasks): array
    {
        return $tasks->groupBy('workflowStep.processFlow.name')
            ->map(function ($workflowTasks, $workflowName) {
                return [
                    'workflow' => $workflowName,
                    'count' => $workflowTasks->count(),
                    'percentage' => 0, // Will be calculated later
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Group tasks by user.
     */
    private function groupTasksByUser(Collection $tasks): array
    {
        return $tasks->groupBy('assignedUser.name')
            ->map(function ($userTasks, $userName) {
                return [
                    'user' => $userName ?: 'Unassigned',
                    'count' => $userTasks->count(),
                    'percentage' => 0, // Will be calculated later
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Calculate average completion time.
     */
    private function calculateAverageCompletionTime(Collection $tasks): ?float
    {
        $tasksWithTimes = $tasks->filter(function ($task) {
            return $task->started_at && $task->completed_at;
        });

        if ($tasksWithTimes->isEmpty()) {
            return null;
        }

        $totalMinutes = $tasksWithTimes->sum(function ($task) {
            return $task->started_at->diffInMinutes($task->completed_at);
        });

        return round($totalMinutes / $tasksWithTimes->count(), 2);
    }

    /**
     * Get daily completion trend.
     */
    private function getDailyCompletionTrend(
        Collection $tasks,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $dailyCompletions = $tasks->groupBy(function ($task) {
            return $task->completed_at->format('Y-m-d');
        });

        $trend = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->format('Y-m-d');
            $trend[] = [
                'date' => $dateString,
                'count' => $dailyCompletions->get($dateString, collect())->count(),
            ];
            $currentDate->addDay();
        }

        return $trend;
    }

    /**
     * Get step breakdown for workflow.
     */
    private function getStepBreakdown(Collection $workflowTasks): array
    {
        return $workflowTasks->groupBy('workflowStep.name')
            ->map(function ($stepTasks, $stepName) {
                $completionTimes = $stepTasks->filter(function ($task) {
                    return $task->started_at && $task->completed_at;
                })->map(function ($task) {
                    return $task->started_at->diffInMinutes($task->completed_at);
                });

                return [
                    'step_name' => $stepName,
                    'count' => $stepTasks->count(),
                    'average_time' => $completionTimes->isNotEmpty() ? round($completionTimes->avg(), 2) : null,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Export report to CSV format.
     */
    public function exportToCsv(array $reportData, string $reportType): string
    {
        $csv = '';
        
        switch ($reportType) {
            case 'completion':
                $csv = $this->exportCompletionReportToCsv($reportData);
                break;
            case 'performance':
                $csv = $this->exportPerformanceReportToCsv($reportData);
                break;
            case 'overdue':
                $csv = $this->exportOverdueReportToCsv($reportData);
                break;
        }

        return $csv;
    }

    /**
     * Export completion report to CSV.
     */
    private function exportCompletionReportToCsv(array $data): string
    {
        $csv = "Task Completion Report\n";
        $csv .= "Period: {$data['period']['start']} to {$data['period']['end']}\n";
        $csv .= "User: {$data['user']}\n";
        $csv .= "Total Completed: {$data['total_completed']}\n\n";

        $csv .= "Completion by Type\n";
        $csv .= "Type,Count\n";
        foreach ($data['completion_by_type'] as $type) {
            $csv .= "{$type['type']},{$type['count']}\n";
        }

        return $csv;
    }

    /**
     * Export performance report to CSV.
     */
    private function exportPerformanceReportToCsv(array $data): string
    {
        $csv = "Task Performance Report\n";
        $csv .= "Period: {$data['period']['start']} to {$data['period']['end']}\n";
        $csv .= "User: {$data['user']}\n";
        $csv .= "Total Assigned: {$data['total_assigned']}\n";
        $csv .= "Completed: {$data['completed']}\n";
        $csv .= "Pending: {$data['pending']}\n";
        $csv .= "In Progress: {$data['in_progress']}\n";
        $csv .= "Overdue: {$data['overdue']}\n";
        $csv .= "Completion Rate: {$data['completion_rate']}%\n";
        $csv .= "Overdue Rate: {$data['overdue_rate']}%\n";

        return $csv;
    }

    /**
     * Export overdue report to CSV.
     */
    private function exportOverdueReportToCsv(array $data): string
    {
        $csv = "Overdue Tasks Report\n";
        $csv .= "Total Overdue: {$data['total_overdue']}\n";
        $csv .= "Hours Threshold: {$data['hours_threshold']}\n\n";

        $csv .= "Overdue by User\n";
        $csv .= "User,Count\n";
        foreach ($data['overdue_by_user'] as $user) {
            $csv .= "{$user['user']},{$user['count']}\n";
        }

        return $csv;
    }
}