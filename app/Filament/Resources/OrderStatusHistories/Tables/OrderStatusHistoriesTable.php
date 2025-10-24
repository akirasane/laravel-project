<?php

namespace App\Filament\Resources\OrderStatusHistories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\DateRangeFilter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class OrderStatusHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.platform_order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->url(fn ($record) => route('filament.admin.resources.orders.orders.view', $record->order)),
                TextColumn::make('order.platform_type')
                    ->label('Platform')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'shopee' => 'orange',
                        'lazada' => 'blue',
                        'shopify' => 'green',
                        'tiktok' => 'pink',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('previous_status')
                    ->label('From Status')
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
                    }),
                TextColumn::make('new_status')
                    ->label('To Status')
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
                    }),
                TextColumn::make('changed_by_type')
                    ->label('Changed By')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'user' => 'primary',
                        'system' => 'info',
                        'api' => 'warning',
                        'webhook' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                TextColumn::make('changedBy.name')
                    ->label('User')
                    ->placeholder('System/API')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_reversible')
                    ->label('Reversible')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-uturn-left')
                    ->falseIcon('heroicon-o-lock-closed')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),
                TextColumn::make('reversed_at')
                    ->label('Reversed At')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Not reversed')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Changed At')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->diffForHumans()),
            ])
            ->filters([
                SelectFilter::make('order.platform_type')
                    ->label('Platform')
                    ->relationship('order', 'platform_type')
                    ->options([
                        'shopee' => 'Shopee',
                        'lazada' => 'Lazada',
                        'shopify' => 'Shopify',
                        'tiktok' => 'TikTok',
                    ])
                    ->multiple(),
                SelectFilter::make('previous_status')
                    ->label('From Status')
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
                SelectFilter::make('new_status')
                    ->label('To Status')
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
                SelectFilter::make('changed_by_type')
                    ->label('Changed By Type')
                    ->options([
                        'user' => 'User',
                        'system' => 'System',
                        'api' => 'API',
                        'webhook' => 'Webhook',
                    ])
                    ->multiple(),
                Filter::make('is_reversible')
                    ->label('Reversible Only')
                    ->query(fn (Builder $query): Builder => $query->where('is_reversible', true)->whereNull('reversed_at')),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('changed_from')
                            ->label('Changed From'),
                        DatePicker::make('changed_until')
                            ->label('Changed Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['changed_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['changed_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('reverseStatus')
                    ->label('Reverse')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->action(function ($record) {
                        // Mark as reversed
                        $record->update(['reversed_at' => now()]);
                        
                        // Revert the order status
                        $record->order->update(['status' => $record->previous_status]);
                        
                        // Create a new history record for the reversal
                        $record->order->statusHistory()->create([
                            'previous_status' => $record->new_status,
                            'new_status' => $record->previous_status,
                            'changed_by_type' => 'user',
                            'changed_by_id' => auth()->id(),
                            'reason' => "Reversed status change from {$record->created_at->format('M j, Y H:i')}",
                            'is_reversible' => false,
                        ]);
                        
                        Notification::make()
                            ->title('Status Reversed')
                            ->body("Order status reverted to {$record->previous_status}")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->is_reversible && !$record->reversed_at),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete History Records')
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
