<?php

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Resources\Pages\ListRecords;

/**
 * تاریخچه تراکنش‌های انبار — فقط‌خواندنی.
 *
 * دکمه «ایجاد» عمداً حذف شد: این Resource فرم ندارد، پس دکمه پنجره‌ای خالی
 * باز می‌کرد و ثبتش با خطای «item_version_id مقدار پیش‌فرض ندارد» شکست
 * می‌خورد. مهم‌تر از آن، ساخت دستی سند تراکنش قاعده ۳ و ۴ پروژه را می‌شکند:
 * هر تراکنش باید از StockMovementService بگذرد تا لات FIFO و موجودی خلاصه
 * با هم هم‌خوان بمانند. ثبت ورود و خروج از صفحه «مدیریت انبار» انجام می‌شود.
 */
class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    public function getSubheading(): ?string
    {
        return __('stock.movements_intro');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
