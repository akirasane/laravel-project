<?php

namespace App\Filament\Resources\TaskAssignmentResource\Pages;

use App\Filament\Resources\TaskAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTaskAssignment extends EditRecord
{
    protected static string $resource = TaskAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}