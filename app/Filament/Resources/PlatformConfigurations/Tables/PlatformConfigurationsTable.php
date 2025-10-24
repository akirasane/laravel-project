<?php

namespace App\Filament\Resources\PlatformConfigurations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Notifications\Notification;

class PlatformConfigurationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
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
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
                TextColumn::make('sync_interval')
                    ->label('Sync Interval')
                    ->formatStateUsing(fn (int $state): string => $state >= 3600 
                        ? round($state / 3600, 1) . ' hours'
                        : round($state / 60) . ' minutes'
                    )
                    ->sortable(),
                TextColumn::make('last_sync')
                    ->label('Last Sync')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Never')
                    ->sortable()
                    ->color(fn ($state) => $state && $state->diffInHours() > 24 ? 'warning' : 'success'),
                TextColumn::make('sync_status')
                    ->label('Sync Status')
                    ->getStateUsing(function ($record) {
                        if (!$record->last_sync) {
                            return 'never';
                        }
                        $hoursAgo = $record->last_sync->diffInHours();
                        if ($hoursAgo > 24) {
                            return 'stale';
                        } elseif ($hoursAgo > 2) {
                            return 'warning';
                        }
                        return 'recent';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'recent' => 'success',
                        'warning' => 'warning',
                        'stale' => 'danger',
                        'never' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'recent' => 'Recent',
                        'warning' => 'Warning',
                        'stale' => 'Stale',
                        'never' => 'Never',
                        default => 'Unknown',
                    }),
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
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->placeholder('All Platforms'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('testConnection')
                    ->label('Test Connection')
                    ->icon('heroicon-o-wifi')
                    ->color('info')
                    ->action(function ($record) {
                        // This would typically test the API connection
                        // For now, we'll just show a notification
                        Notification::make()
                            ->title('Connection Test')
                            ->body("Testing connection to {$record->platform_type}...")
                            ->info()
                            ->send();
                    }),
                Action::make('syncNow')
                    ->label('Sync Now')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function ($record) {
                        // Update last_sync timestamp
                        $record->update(['last_sync' => now()]);
                        
                        Notification::make()
                            ->title('Sync Triggered')
                            ->body("Manual sync initiated for {$record->platform_type}")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->is_active),
                Action::make('toggleStatus')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn ($record) => $record->is_active ? 'warning' : 'success')
                    ->action(function ($record) {
                        $record->update(['is_active' => !$record->is_active]);
                        
                        $status = $record->is_active ? 'activated' : 'deactivated';
                        Notification::make()
                            ->title('Status Updated')
                            ->body("Platform {$record->platform_type} has been {$status}")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\BulkAction::make('bulkActivate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each->update(['is_active' => true]);
                            
                            Notification::make()
                                ->title('Platforms Activated')
                                ->body(count($records) . ' platform(s) have been activated')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\BulkAction::make('bulkDeactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->action(function ($records) {
                            $records->each->update(['is_active' => false]);
                            
                            Notification::make()
                                ->title('Platforms Deactivated')
                                ->body(count($records) . ' platform(s) have been deactivated')
                                ->warning()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('platform_type')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
