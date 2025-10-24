<?php

namespace App\Filament\Resources\PlatformConfigurations\Pages;

use App\Filament\Resources\PlatformConfigurations\PlatformConfigurationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPlatformConfiguration extends ViewRecord
{
    protected static string $resource = PlatformConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
