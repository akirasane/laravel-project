<?php

namespace App\Filament\Resources\OrderStatusHistories;

use App\Filament\Resources\OrderStatusHistories\Pages\CreateOrderStatusHistory;
use App\Filament\Resources\OrderStatusHistories\Pages\EditOrderStatusHistory;
use App\Filament\Resources\OrderStatusHistories\Pages\ListOrderStatusHistories;
use App\Filament\Resources\OrderStatusHistories\Pages\ViewOrderStatusHistory;
use App\Filament\Resources\OrderStatusHistories\Schemas\OrderStatusHistoryForm;
use App\Filament\Resources\OrderStatusHistories\Schemas\OrderStatusHistoryInfolist;
use App\Filament\Resources\OrderStatusHistories\Tables\OrderStatusHistoriesTable;
use App\Models\OrderStatusHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderStatusHistoryResource extends Resource
{
    protected static ?string $model = OrderStatusHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public static function form(Schema $schema): Schema
    {
        return OrderStatusHistoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderStatusHistoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderStatusHistoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrderStatusHistories::route('/'),
            'create' => CreateOrderStatusHistory::route('/create'),
            'view' => ViewOrderStatusHistory::route('/{record}'),
            'edit' => EditOrderStatusHistory::route('/{record}/edit'),
        ];
    }
}
