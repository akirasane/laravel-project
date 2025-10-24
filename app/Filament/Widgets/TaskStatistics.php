<?php

namespace App\Filament\Widgets;

use App\Services\TaskManager;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TaskStatistics extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $taskManager = app(TaskManager::class);
        $userStats = $taskManager->getUserTaskStatistics(auth()->user());
        $overallStats = $taskManager->getOverallTaskStatistics();

        return [
            Stat::make('My Pending Tasks', $userStats['pending'])
                ->description('Tasks waiting to be started')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('My In Progress', $userStats['in_progress'])
                ->description('Tasks currently being worked on')
                ->descriptionIcon('heroicon-m-play')
                ->color('primary'),

            Stat::make('Completed Today', $userStats['completed_today'])
                ->description('Tasks completed today')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('System Pending', $overallStats['total_pending'])
                ->description('Total pending tasks in system')
                ->descriptionIcon('heroicon-m-queue-list')
                ->color('gray'),

            Stat::make('System Overdue', $overallStats['total_overdue'])
                ->description('Tasks overdue (>24h)')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Avg Completion', $overallStats['average_completion_time'] ? round($overallStats['average_completion_time']) . ' min' : 'N/A')
                ->description('Average task completion time')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}