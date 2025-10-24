<?php

namespace App\Filament\Resources\TaskAssignmentResource\Pages;

use App\Filament\Resources\TaskAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaskAssignments extends ListRecords
{
    protected static string $resource = TaskAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}