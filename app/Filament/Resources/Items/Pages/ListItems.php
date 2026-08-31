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

    /**
     * کلیک روی «کالای تمام‌شده» در داشبورد (?stock=out) فیلترِ وضعیت موجودی را
     * همین‌جا فعال می‌کند — چون هیدریتِ فیلتر از URLِ tableFilters همیشه قابل‌اتکا نبود.
     */
    public function mount(): void
    {
        parent::mount();

        if (in_array(request('stock'), ['out', 'low'], true)) {
            $this->tableFilters['stock_state']['value'] = request('stock');
        }

        // کلیک روی تعدادِ کالاهای یک دسته (?category=id) → فیلترِ همان دسته.
        if (filled(request('category'))) {
            $this->tableFilters['item_category_id']['value'] = request('category');
        }
    }

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
