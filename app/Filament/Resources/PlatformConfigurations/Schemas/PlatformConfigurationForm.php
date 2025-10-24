<?php

namespace App\Filament\Resources\PlatformConfigurations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Group;
use Filament\Schemas\Schema;
use Filament\Forms\Get;

class PlatformConfigurationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Platform Information')
                    ->schema([
                        Select::make('platform_type')
                            ->label('Platform')
                            ->options([
                                'shopee' => 'Shopee',
                                'lazada' => 'Lazada',
                                'shopify' => 'Shopify',
                                'tiktok' => 'TikTok'
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('credentials', [])),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable/disable synchronization for this platform'),
                    ])
                    ->columns(2),
                
                Section::make('API Credentials')
                    ->schema([
                        KeyValue::make('credentials')
                            ->label('Credentials')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Credential')
                            ->required()
                            ->columnSpanFull()
                            ->helperText(function (Get $get) {
                                return match ($get('platform_type')) {
                                    'shopee' => 'Required fields: partner_id, partner_key, shop_id',
                                    'lazada' => 'Required fields: app_key, app_secret, access_token',
                                    'shopify' => 'Required fields: shop_domain, access_token, api_key, api_secret',
                                    'tiktok' => 'Required fields: app_key, app_secret, access_token, shop_id',
                                    default => 'Enter the required API credentials for the selected platform',
                                };
                            }),
                    ]),
                
                Section::make('Synchronization Settings')
                    ->schema([
                        Group::make([
                            TextInput::make('sync_interval')
                                ->label('Sync Interval (seconds)')
                                ->required()
                                ->numeric()
                                ->minValue(60)
                                ->maxValue(86400)
                                ->default(300)
                                ->suffix('seconds')
                                ->helperText('Minimum: 60 seconds (1 minute), Maximum: 86400 seconds (24 hours)'),
                            Placeholder::make('sync_interval_display')
                                ->label('Interval Display')
                                ->content(function (Get $get) {
                                    $seconds = $get('sync_interval') ?? 300;
                                    if ($seconds >= 3600) {
                                        return round($seconds / 3600, 1) . ' hours';
                                    } elseif ($seconds >= 60) {
                                        return round($seconds / 60) . ' minutes';
                                    }
                                    return $seconds . ' seconds';
                                }),
                        ])->columns(2),
                        
                        DateTimePicker::make('last_sync')
                            ->label('Last Sync')
                            ->disabled()
                            ->helperText('This field is automatically updated when synchronization occurs'),
                    ]),
                
                Section::make('Additional Settings')
                    ->schema([
                        KeyValue::make('settings')
                            ->label('Platform Settings')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Setting')
                            ->columnSpanFull()
                            ->helperText('Additional platform-specific configuration options'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
