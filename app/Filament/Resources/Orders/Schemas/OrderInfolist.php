<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Information')
                    ->schema([
                        TextEntry::make('platform_order_id')
                            ->label('Order ID')
                            ->copyable()
                            ->weight('bold'),
                        TextEntry::make('platform_type')
                            ->label('Platform')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'shopee' => 'orange',
                                'lazada' => 'blue',
                                'shopify' => 'green',
                                'tiktok' => 'pink',
                                default => 'gray',
                            }),
                        TextEntry::make('order_date')
                            ->label('Order Date')
                            ->dateTime('F j, Y \a\t g:i A'),
                        TextEntry::make('total_amount')
                            ->label('Total Amount')
                            ->money(fn ($record) => $record->currency)
                            ->size('lg')
                            ->weight('bold'),
                        TextEntry::make('status')
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
                        TextEntry::make('workflow_status')
                            ->label('Workflow Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'new' => 'warning',
                                'in_progress' => 'primary',
                                'completed' => 'success',
                                'on_hold' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('sync_status')
                            ->label('Sync Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'synced' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                default => 'gray',
                            }),
                    ])
                    ->columns(3),
                
                Section::make('Customer Information')
                    ->schema([
                        TextEntry::make('customer_name')
                            ->label('Name')
                            ->placeholder('Not provided'),
                        TextEntry::make('customer_email')
                            ->label('Email')
                            ->placeholder('Not provided')
                            ->copyable(),
                        TextEntry::make('customer_phone')
                            ->label('Phone')
                            ->placeholder('Not provided')
                            ->copyable(),
                        TextEntry::make('shipping_address')
                            ->label('Shipping Address')
                            ->placeholder('Not provided')
                            ->columnSpanFull(),
                        TextEntry::make('billing_address')
                            ->label('Billing Address')
                            ->placeholder('Not provided')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                
                Section::make('Order Items')
                    ->schema([
                        RepeatableEntry::make('orderItems')
                            ->label('')
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label('Product')
                                    ->weight('bold'),
                                TextEntry::make('product_sku')
                                    ->label('SKU')
                                    ->placeholder('N/A'),
                                TextEntry::make('quantity')
                                    ->label('Qty')
                                    ->numeric(),
                                TextEntry::make('unit_price')
                                    ->label('Unit Price')
                                    ->money(fn ($record) => $record->order->currency ?? 'USD'),
                                TextEntry::make('total_price')
                                    ->label('Total')
                                    ->money(fn ($record) => $record->order->currency ?? 'USD')
                                    ->weight('bold'),
                            ])
                            ->columns(5)
                            ->contained(false),
                    ])
                    ->collapsible(),
                
                Section::make('Additional Information')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                        KeyValueEntry::make('raw_data')
                            ->label('Platform Raw Data')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
                
                Section::make('System Information')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('F j, Y \a\t g:i A'),
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('F j, Y \a\t g:i A'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
