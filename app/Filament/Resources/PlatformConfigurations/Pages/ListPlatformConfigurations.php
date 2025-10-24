<?php

namespace App\Filament\Resources\PlatformConfigurations\Pages;

use App\Filament\Resources\PlatformConfigurations\PlatformConfigurationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformConfigurations extends ListRecords
{
    protected static string $resource = PlatformConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
