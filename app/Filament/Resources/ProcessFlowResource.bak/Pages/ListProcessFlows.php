<?php

namespace App\Filament\Resources\ProcessFlowResource\Pages;

use App\Filament\Resources\ProcessFlowResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcessFlows extends ListRecords
{
    protected static string $resource = ProcessFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}