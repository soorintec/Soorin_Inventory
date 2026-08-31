<?php

namespace App\Filament\Resources\Items\Tables;

use App\Filament\Resources\Items\Pages\ItemKardex;
use App\Models\Item;
use App\Models\Warehouse;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * «موجودی انبار» — فهرست کالاها همراه موجودی.
 *
 * هر سطر یک پنجره آبشاری دارد که ورژن‌ها، محل استقرار، موجودی هر ورژن، قیمت
 * ارزی و یادداشت انبار را نشان می‌دهد. عمداً برای همه کالاها باز می‌شود، حتی
 * تک‌ورژنی‌ها، چون نبودِ گاه‌به‌گاهِ پنجره گیج‌کننده است.
 *
 * ویرایش کالا اینجا نیست؛ از «مدیریت انبار» انجام می‌شود.
 */
class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // موجودی و حد هشدار در همان کوئری فهرست جمع زده می‌شوند تا هم
            // ستون وضعیت بدون کوئری اضافه ساخته شود و هم بشود روی آن مرتب کرد.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['category', 'versions'])
                ->withSum('balances as stock_total', 'quantity')
                ->withMax('versions as min_threshold', 'min_stock'))
            // ستون‌های ساده (نه Split) تا سطر عنوان (هدر) نمایش داده شود — خواسته
            // مالک پروژه. فیلامنت با هر مؤلفهٔ چیدمانی (Split/Panel) هدر را حذف
            // می‌کند، پس پنجره آبشاری قبلی جایش را به دکمهٔ «ورژن‌ها» داد که همان
            // جدول را در یک پنجره باز می‌کند. ستون‌های کم‌اهمیت روی موبایل پنهانند.
            ->columns([
                static::statusColumn(),

                ImageColumn::make('image')
                    ->label(__('items.image'))
                    ->disk('items')
                    ->circular()
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('name')
                    ->label(__('items.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Item $record) => $record->code),

                TextColumn::make('category.name')
                    ->label(__('items.category_label'))
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('versions_count')
                    ->label(__('items.version_plural'))
                    ->counts('versions')
                    ->formatStateUsing(fn ($state) => Jalali::quantity($state) . ' ' . __('items.version_label'))
                    ->badge()
                    ->color('info')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('stock_total')
                    ->label(__('items.total_stock'))
                    ->state(fn (Item $record) => Jalali::quantity($record->stock_total ?? 0) . ' ' . $record->unit)
                    ->badge()
                    ->color(fn (Item $record) => static::colourFor($record->stockStatus()))
                    ->sortable(),
            ])
            ->recordActions([
                // ورژن‌ها در یک پنجره باز می‌شوند (با فاصله‌گذاری درست ستون‌ها)
                Action::make('versions')
                    ->label(__('items.version_plural'))
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color('primary')
                    ->modalHeading(fn (Item $record) => $record->name . ' — ' . __('items.version_plural'))
                    ->modalContent(fn (Item $record) => view('filament.tables.item-versions-modal', ['item' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('common.close')),

                // کاردکس: دفترِ کاملِ ورود/خروجِ همین کالا با ماندهٔ در حرکت.
                Action::make('kardex')
                    ->label(__('stock.kardex'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('gray')
                    ->url(fn (Item $record) => ItemKardex::getUrl(['record' => $record])),
            ])
            ->filters([
                SelectFilter::make('item_category_id')
                    ->label(__('items.category_label'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                // وضعیت موجودی — از داشبورد هم با کلیک روی «کالای تمام‌شده» فعال می‌شود.
                SelectFilter::make('stock_state')
                    ->label(__('items.stock_state'))
                    ->options([
                        'out' => __('items.statuses.out'),
                        'low' => __('items.statuses.low'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! in_array($value, ['out', 'low'], true)) {
                            return $query;
                        }

                        $total = '(SELECT COALESCE(SUM(sb.quantity), 0) FROM stock_balances sb
                                    JOIN item_versions iv ON iv.id = sb.item_version_id
                                   WHERE iv.item_id = items.id AND iv.deleted_at IS NULL)';
                        $min = '(SELECT COALESCE(MAX(iv2.min_stock), 0) FROM item_versions iv2
                                  WHERE iv2.item_id = items.id AND iv2.deleted_at IS NULL)';

                        return $value === 'out'
                            ? $query->whereRaw("$total <= 0")
                            : $query->whereRaw("$total > 0 AND $min > 0 AND $total <= $min");
                    }),

                // جستجو بر اساس انباری که کالا در آن موجودی دارد
                SelectFilter::make('warehouse')
                    ->label(__('stock.warehouse'))
                    ->options(fn () => Warehouse::where('is_active', true)->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $warehouseId) => $q->whereHas(
                            'versions.balances',
                            fn (Builder $b) => $b->where('warehouse_id', $warehouseId)->where('quantity', '>', 0),
                        ),
                    )),

                Filter::make('in_stock')
                    ->label(__('items.only_in_stock'))
                    ->query(fn (Builder $query) => $query->whereHas(
                        'versions.balances',
                        fn (Builder $b) => $b->where('quantity', '>', 0),
                    ))
                    ->toggle(),

                // میان‌بر همان چیزی که ستون وضعیت نشان می‌دهد: قرمزها و زردها
                Filter::make('low_stock')
                    ->label(__('items.only_low_stock'))
                    ->query(fn (Builder $query) => $query->whereRaw(
                        '(SELECT COALESCE(SUM(sb.quantity), 0)
                            FROM stock_balances sb
                            JOIN item_versions iv ON iv.id = sb.item_version_id
                           WHERE iv.item_id = items.id AND iv.deleted_at IS NULL)
                         <= COALESCE((SELECT MAX(iv2.min_stock) FROM item_versions iv2
                                       WHERE iv2.item_id = items.id AND iv2.deleted_at IS NULL), 0)',
                    ))
                    ->toggle(),

                Filter::make('imported')
                    ->label(__('items.only_imported'))
                    // وارداتی یعنی قیمت ارزی (نه ریالی)؛ حالا که ریال هم می‌تواند
                    // در fx_price بنشیند، ارز غیرریالی ملاک است.
                    ->query(fn (Builder $query) => $query->whereHas(
                        'versions',
                        fn (Builder $v) => $v->whereNotNull('fx_price')->where('fx_price', '>', 0)
                            ->where('fx_currency', '!=', 'IRR'),
                    ))
                    ->toggle(),
            ])
            // بدون این، فیلترِ آمده از URL (کلیک روی «کالای تمام‌شده» در داشبورد)
            // تا زدنِ دکمهٔ «اعمال» اثر نمی‌کرد و همهٔ کالاها نشان داده می‌شد.
            ->deferFilters(false)
            ->defaultSort('name')
            ->emptyStateHeading(__('items.empty_items'));
    }

    /**
     * دایره رنگی وضعیت موجودی.
     *
     * قرمز: تمام شده · زرد: روی حد هشدار یا کمتر · سبز: کافی.
     *
     * مرتب‌سازی در SQL انجام می‌شود (نه روی مجموعه نتیجه) تا صفحه‌بندی هم
     * درست کار کند؛ صعودی یعنی قرمزها بالای بالا، بعد زردها، بعد سبزها.
     */
    private static function statusColumn(): TextColumn
    {
        return TextColumn::make('stock_status')
            ->label(__('items.status'))
            ->state(fn (Item $record) => '●')
            ->color(fn (Item $record) => static::colourFor($record->stockStatus()))
            ->tooltip(fn (Item $record) => __('items.statuses.' . $record->stockStatus()))
            ->size('lg')
            ->grow(false)
            ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw(
                'CASE
                    WHEN COALESCE(stock_total, 0) <= 0 THEN 0
                    WHEN COALESCE(min_threshold, 0) > 0 AND COALESCE(stock_total, 0) <= min_threshold THEN 1
                    ELSE 2
                 END ' . ($direction === 'desc' ? 'desc' : 'asc'),
            ));
    }

    private static function colourFor(string $status): string
    {
        return match ($status) {
            Item::STATUS_OUT => 'danger',
            Item::STATUS_LOW => 'warning',
            default          => 'success',
        };
    }
}
