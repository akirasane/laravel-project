<?php

namespace App\Filament\Resources\PlatformConfigurations\Pages;

use App\Filament\Resources\PlatformConfigurations\PlatformConfigurationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPlatformConfiguration extends EditRecord
{
    protected static string $resource = PlatformConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
