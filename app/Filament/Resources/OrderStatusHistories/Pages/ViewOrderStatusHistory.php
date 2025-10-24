<?php

namespace App\Filament\Resources\OrderStatusHistories\Pages;

use App\Filament\Resources\OrderStatusHistories\OrderStatusHistoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrderStatusHistory extends ViewRecord
{
    protected static string $resource = OrderStatusHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
