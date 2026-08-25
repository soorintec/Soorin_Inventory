<?php

namespace App\Filament\Resources\StockBalances;

use App\Enums\Permission;
use App\Filament\Resources\StockBalances\Pages\ListStockBalances;
use App\Filament\Resources\StockBalances\Tables\StockBalancesTable;
use App\Models\StockBalance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * موجودی جاری — فقط‌خواندنی. ثبت/تغییر موجودی همیشه از طریق اکشن‌های
 * صفحه (ثبت موجودی اولیه/خروج/انتقال) و StockMovementService انجام
 * می‌شود، نه فرم ویرایش مستقیم — چون بدون سند حرکت، FIFO و تاریخچه
 * از موجودی جدا می‌افتد.
 */
class StockBalanceResource extends Resource
{
    protected static ?string $model = StockBalance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 11;

    public static function getModelLabel(): string
    {
        return __('stock.balance_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stock.balance_plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('stock.manage_nav');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('items.nav_group');
    }

    public static function table(Table $table): Table
    {
        return StockBalancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockBalances::route('/')];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewStock->value) ?? false;
    }
}
