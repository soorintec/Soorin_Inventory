<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use App\Filament\Widgets\RecentActivityWidget;
use Filament\Resources\Pages\ListRecords;

/**
 * «موجودی انبار» — فهرست کالاها با موجودی و پنجره آبشاری ورژن‌ها.
 *
 * دکمه «ایجاد» عمداً اینجا نیست: ساخت و ویرایش کالا به «مدیریت انبار» منتقل
 * شد تا این صفحه فقط برای دیدن و جستجوی موجودی بماند.
 */
class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    public function getTitle(): string
    {
        return __('items.nav_label');
    }

    public function getSubheading(): ?string
    {
        return __('items.stock_intro');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** باکس «آخرین تغییرات» بالای فهرست موجودی. */
    protected function getHeaderWidgets(): array
    {
        return [RecentActivityWidget::class];
    }
}
