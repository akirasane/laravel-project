<?php

namespace App\Filament\Resources\PlatformConfigurations;

use App\Filament\Resources\PlatformConfigurations\Pages\CreatePlatformConfiguration;
use App\Filament\Resources\PlatformConfigurations\Pages\EditPlatformConfiguration;
use App\Filament\Resources\PlatformConfigurations\Pages\ListPlatformConfigurations;
use App\Filament\Resources\PlatformConfigurations\Pages\ViewPlatformConfiguration;
use App\Filament\Resources\PlatformConfigurations\Schemas\PlatformConfigurationForm;
use App\Filament\Resources\PlatformConfigurations\Schemas\PlatformConfigurationInfolist;
use App\Filament\Resources\PlatformConfigurations\Tables\PlatformConfigurationsTable;
use App\Models\PlatformConfiguration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PlatformConfigurationResource extends Resource
{
    protected static ?string $model = PlatformConfiguration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $recordTitleAttribute = 'platform_type';

    public static function form(Schema $schema): Schema
    {
        return PlatformConfigurationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlatformConfigurationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformConfigurationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformConfigurations::route('/'),
            'create' => CreatePlatformConfiguration::route('/create'),
            'view' => ViewPlatformConfiguration::route('/{record}'),
            'edit' => EditPlatformConfiguration::route('/{record}/edit'),
        ];
    }
}
