<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\DateRangeFilter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Colors\Color;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform_order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('platform_type')
                    ->label('Platform')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'shopee' => 'orange',
                        'lazada' => 'blue',
                        'shopify' => 'green',
                        'tiktok' => 'pink',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'processing' => 'primary',
                        'shipped' => 'success',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('workflow_status')
                    ->label('Workflow')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'warning',
                        'in_progress' => 'primary',
                        'completed' => 'success',
                        'on_hold' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('order_date')
                    ->label('Order Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sync_status')
                    ->label('Sync')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'synced' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('platform_type')
                    ->label('Platform')
                    ->options([
                        'shopee' => 'Shopee',
                        'lazada' => 'Lazada',
                        'shopify' => 'Shopify',
                        'tiktok' => 'TikTok',
                    ])
                    ->multiple(),
                SelectFilter::make('status')
                    ->label('Order Status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),
                SelectFilter::make('workflow_status')
                    ->label('Workflow Status')
                    ->options([
                        'new' => 'New',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'on_hold' => 'On Hold',
                    ])
                    ->multiple(),
                SelectFilter::make('sync_status')
                    ->label('Sync Status')
                    ->options([
                        'synced' => 'Synced',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                    ])
                    ->multiple(),
                Filter::make('order_date')
                    ->form([
                        DatePicker::make('order_from')
                            ->label('Order Date From'),
                        DatePicker::make('order_until')
                            ->label('Order Date Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['order_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('order_date', '>=', $date),
                            )
                            ->when(
                                $data['order_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('order_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('updateStatus')
                    ->label('Update Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->form([
                        \Filament\Forms\Components\Select::make('status')
                            ->label('New Status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'processing' => 'Processing',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Reason for Status Change')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $previousStatus = $record->status;
                        $record->update(['status' => $data['status']]);
                        
                        // Create status history record
                        $record->statusHistory()->create([
                            'previous_status' => $previousStatus,
                            'new_status' => $data['status'],
                            'changed_by_type' => 'user',
                            'changed_by_id' => auth()->id(),
                            'reason' => $data['reason'] ?? null,
                            'is_reversible' => true,
                        ]);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\BulkAction::make('bulkUpdateStatus')
                        ->label('Update Status')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->form([
                            \Filament\Forms\Components\Select::make('status')
                                ->label('New Status')
                                ->options([
                                    'pending' => 'Pending',
                                    'confirmed' => 'Confirmed',
                                    'processing' => 'Processing',
                                    'shipped' => 'Shipped',
                                    'delivered' => 'Delivered',
                                    'cancelled' => 'Cancelled',
                                    'refunded' => 'Refunded',
                                ])
                                ->required(),
                            \Filament\Forms\Components\Textarea::make('reason')
                                ->label('Reason for Status Change')
                                ->rows(3),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                $previousStatus = $record->status;
                                $record->update(['status' => $data['status']]);
                                
                                // Create status history record
                                $record->statusHistory()->create([
                                    'previous_status' => $previousStatus,
                                    'new_status' => $data['status'],
                                    'changed_by_type' => 'user',
                                    'changed_by_id' => auth()->id(),
                                    'reason' => $data['reason'] ?? null,
                                    'is_reversible' => true,
                                ]);
                            }
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
