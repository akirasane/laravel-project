<?php

namespace App\Filament\Resources\OrderStatusHistories\Pages;

use App\Filament\Resources\OrderStatusHistories\OrderStatusHistoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrderStatusHistories extends ListRecords
{
    protected static string $resource = OrderStatusHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
