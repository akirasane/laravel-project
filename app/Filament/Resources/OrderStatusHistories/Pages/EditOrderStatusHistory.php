<?php

namespace App\Filament\Resources\OrderStatusHistories\Pages;

use App\Filament\Resources\OrderStatusHistories\OrderStatusHistoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrderStatusHistory extends EditRecord
{
    protected static string $resource = OrderStatusHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
