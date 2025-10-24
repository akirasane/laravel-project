<?php

namespace App\Filament\Resources\PlatformConfigurations\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Schemas\Schema;

class PlatformConfigurationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Platform Information')
                    ->schema([
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
                        IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                        TextEntry::make('sync_interval')
                            ->label('Sync Interval')
                            ->formatStateUsing(fn (int $state): string => $state >= 3600 
                                ? round($state / 3600, 1) . ' hours'
                                : round($state / 60) . ' minutes'
                            ),
                    ])
                    ->columns(3),
                
                Section::make('Synchronization Status')
                    ->schema([
                        TextEntry::make('last_sync')
                            ->label('Last Sync')
                            ->dateTime('F j, Y \a\t g:i A')
                            ->placeholder('Never synchronized')
                            ->color(fn ($state) => $state && $state->diffInHours() > 24 ? 'warning' : 'success'),
                        TextEntry::make('sync_health')
                            ->label('Sync Health')
                            ->getStateUsing(function ($record) {
                                if (!$record->last_sync) {
                                    return 'Never synchronized';
                                }
                                $hoursAgo = $record->last_sync->diffInHours();
                                if ($hoursAgo > 24) {
                                    return 'Stale (over 24 hours ago)';
                                } elseif ($hoursAgo > 2) {
                                    return 'Warning (over 2 hours ago)';
                                }
                                return 'Recent (within 2 hours)';
                            })
                            ->badge()
                            ->color(function ($record) {
                                if (!$record->last_sync) return 'gray';
                                $hoursAgo = $record->last_sync->diffInHours();
                                if ($hoursAgo > 24) return 'danger';
                                if ($hoursAgo > 2) return 'warning';
                                return 'success';
                            }),
                        TextEntry::make('next_sync')
                            ->label('Next Sync (Estimated)')
                            ->getStateUsing(function ($record) {
                                if (!$record->is_active) {
                                    return 'Disabled';
                                }
                                if (!$record->last_sync) {
                                    return 'Pending first sync';
                                }
                                return $record->last_sync->addSeconds($record->sync_interval)->format('F j, Y \a\t g:i A');
                            }),
                    ])
                    ->columns(3),
                
                Section::make('API Credentials')
                    ->schema([
                        KeyValueEntry::make('credentials')
                            ->label('Configured Credentials')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull()
                            ->getStateUsing(function ($record) {
                                // Mask sensitive values for display
                                $credentials = $record->credentials ?? [];
                                $masked = [];
                                foreach ($credentials as $key => $value) {
                                    if (in_array(strtolower($key), ['password', 'secret', 'key', 'token'])) {
                                        $masked[$key] = str_repeat('*', min(strlen($value), 8)) . ' (masked)';
                                    } else {
                                        $masked[$key] = $value;
                                    }
                                }
                                return $masked;
                            }),
                    ])
                    ->collapsible(),
                
                Section::make('Additional Settings')
                    ->schema([
                        KeyValueEntry::make('settings')
                            ->label('Platform Settings')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->columnSpanFull()
                            ->placeholder('No additional settings configured'),
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
