<?php

namespace App\Filament\Resources\OrderStatusHistories\Pages;

use App\Filament\Resources\OrderStatusHistories\OrderStatusHistoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrderStatusHistory extends CreateRecord
{
    protected static string $resource = OrderStatusHistoryResource::class;
}
