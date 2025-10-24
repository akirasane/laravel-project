<?php

namespace App\Filament\Resources\OrderStatusHistories\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Schemas\Schema;

class OrderStatusHistoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Status Change Information')
                    ->schema([
                        TextEntry::make('order.platform_order_id')
                            ->label('Order ID')
                            ->copyable()
                            ->url(fn ($record) => route('filament.admin.resources.orders.orders.view', $record->order)),
                        TextEntry::make('order.platform_type')
                            ->label('Platform')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'shopee' => 'orange',
                                'lazada' => 'blue',
                                'shopify' => 'green',
                                'tiktok' => 'pink',
                                default => 'gray',
                            }),
                        TextEntry::make('previous_status')
                            ->label('Previous Status')
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
                        TextEntry::make('new_status')
                            ->label('New Status')
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
                        TextEntry::make('created_at')
                            ->label('Changed At')
                            ->dateTime('F j, Y \a\t g:i A')
                            ->description(fn ($record) => $record->created_at->diffForHumans()),
                    ])
                    ->columns(3),
                
                Section::make('Change Details')
                    ->schema([
                        TextEntry::make('changed_by_type')
                            ->label('Changed By Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'user' => 'primary',
                                'system' => 'info',
                                'api' => 'warning',
                                'webhook' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        TextEntry::make('changedBy.name')
                            ->label('User')
                            ->placeholder('System/API/Webhook'),
                        TextEntry::make('changedBy.email')
                            ->label('User Email')
                            ->placeholder('N/A')
                            ->copyable(),
                        TextEntry::make('reason')
                            ->label('Reason')
                            ->placeholder('No reason provided')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                
                Section::make('Reversal Information')
                    ->schema([
                        IconEntry::make('is_reversible')
                            ->label('Reversible')
                            ->boolean()
                            ->trueIcon('heroicon-o-arrow-uturn-left')
                            ->falseIcon('heroicon-o-lock-closed')
                            ->trueColor('success')
                            ->falseColor('gray'),
                        TextEntry::make('reversed_at')
                            ->label('Reversed At')
                            ->dateTime('F j, Y \a\t g:i A')
                            ->placeholder('Not reversed')
                            ->description(fn ($record) => $record->reversed_at ? $record->reversed_at->diffForHumans() : null),
                        TextEntry::make('reversal_status')
                            ->label('Reversal Status')
                            ->getStateUsing(function ($record) {
                                if (!$record->is_reversible) {
                                    return 'Not reversible';
                                }
                                if ($record->reversed_at) {
                                    return 'Reversed';
                                }
                                return 'Can be reversed';
                            })
                            ->badge()
                            ->color(function ($record) {
                                if (!$record->is_reversible) return 'gray';
                                if ($record->reversed_at) return 'warning';
                                return 'success';
                            }),
                    ])
                    ->columns(3),
                
                Section::make('Additional Data')
                    ->schema([
                        KeyValueEntry::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull()
                            ->placeholder('No additional metadata'),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->metadata)),
                
                Section::make('Related Order Information')
                    ->schema([
                        TextEntry::make('order.customer_name')
                            ->label('Customer'),
                        TextEntry::make('order.total_amount')
                            ->label('Order Total')
                            ->money(fn ($record) => $record->order->currency),
                        TextEntry::make('order.order_date')
                            ->label('Order Date')
                            ->dateTime('M j, Y'),
                        TextEntry::make('order.status')
                            ->label('Current Status')
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
                    ])
                    ->columns(4)
                    ->collapsible(),
            ]);
    }
}
