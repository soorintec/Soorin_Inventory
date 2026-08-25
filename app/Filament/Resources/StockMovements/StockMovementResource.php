<?php

namespace App\Filament\Resources\StockMovements;

use App\Enums\Permission;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * تاریخچه حرکات انبار — فقط‌خواندنی. هیچ رکوردی حذف/ویرایش نمی‌شود؛
 * اصلاح فقط با سند معکوس از طریق اکشن‌های صفحه موجودی انجام می‌شود.
 */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static ?int $navigationSort = 12;

    public static function getModelLabel(): string
    {
        return __('stock.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stock.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('items.nav_group');
    }

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockMovements::route('/')];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewStock->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
