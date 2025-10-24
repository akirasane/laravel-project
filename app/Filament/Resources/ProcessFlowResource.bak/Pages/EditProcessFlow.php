<?php

namespace App\Filament\Resources\ProcessFlowResource\Pages;

use App\Filament\Resources\ProcessFlowResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProcessFlow extends EditRecord
{
    protected static string $resource = ProcessFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('manage_steps')
                ->label('Manage Steps')
                ->icon('heroicon-o-list-bullet')
                ->url(fn (): string => ProcessFlowResource::getUrl('steps', ['record' => $this->record])),
        ];
    }
}