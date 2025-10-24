<?php

namespace App\Filament\Resources\ProcessFlowResource\Pages;

use App\Filament\Resources\ProcessFlowResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProcessFlow extends CreateRecord
{
    protected static string $resource = ProcessFlowResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('steps', ['record' => $this->record]);
    }
}