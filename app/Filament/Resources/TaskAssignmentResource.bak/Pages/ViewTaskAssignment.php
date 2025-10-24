<?php

namespace App\Filament\Resources\TaskAssignmentResource\Pages;

use App\Filament\Resources\TaskAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTaskAssignment extends ViewRecord
{
    protected static string $resource = TaskAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}