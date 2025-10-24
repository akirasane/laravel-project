<?php

namespace App\Filament\Resources\OrderStatusHistories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;

class OrderStatusHistoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Status Change Information')
                    ->description('This form is primarily for viewing audit trail records. Most fields are automatically populated.')
                    ->schema([
                        Select::make('order_id')
                            ->label('Order')
                            ->relationship('order', 'platform_order_id')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($context) => $context === 'edit'),
                        Select::make('previous_status')
                            ->label('Previous Status')
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
                            ->disabled(fn ($context) => $context === 'edit'),
                        Select::make('new_status')
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
                            ->required()
                            ->disabled(fn ($context) => $context === 'edit'),
                    ])
                    ->columns(3),
                
                Section::make('Change Details')
                    ->schema([
                        Select::make('changed_by_type')
                            ->label('Changed By Type')
                            ->options([
                                'user' => 'User',
                                'system' => 'System',
                                'api' => 'API',
                                'webhook' => 'Webhook',
                            ])
                            ->required()
                            ->disabled(fn ($context) => $context === 'edit'),
                        Select::make('changed_by_id')
                            ->label('User')
                            ->relationship('changedBy', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->disabled(fn ($context) => $context === 'edit'),
                        Textarea::make('reason')
                            ->label('Reason')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                
                Section::make('Reversal Settings')
                    ->schema([
                        Toggle::make('is_reversible')
                            ->label('Is Reversible')
                            ->helperText('Whether this status change can be reversed')
                            ->default(true)
                            ->disabled(fn ($context) => $context === 'edit'),
                        DateTimePicker::make('reversed_at')
                            ->label('Reversed At')
                            ->disabled()
                            ->helperText('Automatically set when the status change is reversed'),
                    ])
                    ->columns(2),
                
                Section::make('Additional Data')
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Metadata')
                            ->columnSpanFull()
                            ->helperText('Additional data related to this status change'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
