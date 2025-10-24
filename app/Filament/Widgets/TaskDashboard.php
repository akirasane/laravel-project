<?php

namespace App\Filament\Widgets;

use App\Models\TaskAssignment;
use App\Services\TaskManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TaskDashboard extends BaseWidget
{
    protected static ?string $heading = 'My Tasks';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TaskAssignment::query()
                    ->where('assigned_to', auth()->id())
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->with(['order', 'workflowStep.processFlow'])
                    ->orderByRaw("FIELD(status, 'in_progress', 'pending')")
                    ->orderBy('assigned_at')
            )
            ->columns([
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'primary' => 'in_progress',
                    ]),
                
                Tables\Columns\TextColumn::make('workflowStep.name')
                    ->label('Task')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('workflowStep.processFlow.name')
                    ->label('Workflow')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('order.platform_order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('order.customer_name')
                    ->label('Customer')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('order.total_amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->order->currency ?? 'USD')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('assigned_at')
                    ->label('Assigned')
                    ->dateTime()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime()
                    ->placeholder('Not started'),
            ])
            ->actions([
                Tables\Actions\Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (TaskAssignment $record) => $record->status === 'pending')
                    ->action(function (TaskAssignment $record) {
                        $taskManager = app(TaskManager::class);
                        $taskManager->startTask($record, auth()->user());
                        $this->dispatch('task-updated');
                    }),
                
                Tables\Actions\Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (TaskAssignment $record) => $record->status === 'in_progress')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Completion Notes')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (TaskAssignment $record, array $data) {
                        $taskManager = app(TaskManager::class);
                        $taskManager->completeTask($record, $data, auth()->user());
                        $this->dispatch('task-updated');
                    }),
                
                Tables\Actions\Action::make('view_order')
                    ->label('View Order')
                    ->icon('heroicon-o-eye')
                    ->url(fn (TaskAssignment $record) => route('filament.admin.resources.orders.view', $record->order)),
            ])
            ->emptyStateHeading('No pending tasks')
            ->emptyStateDescription('You have no tasks assigned at the moment.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }
}