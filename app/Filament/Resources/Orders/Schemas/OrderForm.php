<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Information')
                    ->schema([
                        TextInput::make('platform_order_id')
                            ->label('Platform Order ID')
                            ->required()
                            ->maxLength(255),
                        Select::make('platform_type')
                            ->label('Platform')
                            ->options([
                                'shopee' => 'Shopee',
                                'lazada' => 'Lazada',
                                'shopify' => 'Shopify',
                                'tiktok' => 'TikTok'
                            ])
                            ->required(),
                        DateTimePicker::make('order_date')
                            ->label('Order Date')
                            ->required()
                            ->default(now()),
                        TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        TextInput::make('currency')
                            ->label('Currency')
                            ->required()
                            ->default('USD')
                            ->length(3)
                            ->placeholder('USD'),
                    ])
                    ->columns(2),
                
                Section::make('Status Information')
                    ->schema([
                        Select::make('status')
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
                            ->required()
                            ->default('pending'),
                        Select::make('workflow_status')
                            ->label('Workflow Status')
                            ->options([
                                'new' => 'New',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'on_hold' => 'On Hold',
                            ])
                            ->default('new')
                            ->required(),
                        Select::make('sync_status')
                            ->label('Sync Status')
                            ->options([
                                'synced' => 'Synced',
                                'pending' => 'Pending',
                                'failed' => 'Failed'
                            ])
                            ->default('pending')
                            ->required(),
                    ])
                    ->columns(3),
                
                Section::make('Customer Information')
                    ->schema([
                        TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('customer_email')
                            ->label('Customer Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('customer_phone')
                            ->label('Customer Phone')
                            ->tel()
                            ->maxLength(50),
                    ])
                    ->columns(2),
                
                Section::make('Address Information')
                    ->schema([
                        Textarea::make('shipping_address')
                            ->label('Shipping Address')
                            ->rows(3)
                            ->maxLength(500),
                        Textarea::make('billing_address')
                            ->label('Billing Address')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->columns(2),
                
                Section::make('Additional Information')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        KeyValue::make('raw_data')
                            ->label('Platform Raw Data')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
