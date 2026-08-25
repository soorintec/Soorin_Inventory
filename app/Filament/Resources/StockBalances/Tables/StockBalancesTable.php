<?php

namespace App\Filament\Resources\StockBalances\Tables;

use App\Enums\Permission;
use App\Filament\Resources\Items\ItemResource;
use App\Models\StockBalance;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockBalancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('itemVersion.item.name')
                    ->label(__('items.label'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    // یادداشت انبار زیر نام کالا می‌آید — «یک عدد معیوب»،
                    // «در حال اتمام». اطلاعاتی که موقع برداشت باید دیده شود.
                    ->description(fn ($record) => $record->itemVersion?->notes),

                TextColumn::make('itemVersion.version_code')
                    ->label(__('items.version_label'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label(__('stock.warehouse'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                // آدرس قفسه — کاربر انبار با همین ستون کالا را پیدا می‌کند
                TextColumn::make('itemVersion.location')
                    ->label(__('items.location'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('quantity')
                    ->label(__('stock.quantity'))
                    ->formatStateUsing(fn ($state) => Jalali::quantity($state))
                    ->sortable()
                    ->weight('bold'),

                // قیمت ثبت‌شده روی همین ورژن — با ارز خودش (ریال/دلار/یوان)
                TextColumn::make('itemVersion.fx_price')
                    ->label(__('items.fx_price'))
                    ->state(fn (StockBalance $record) => $record->itemVersion?->fxPriceLabel())
                    ->sortable()
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('reserved')
                    ->label(__('stock.reserved'))
                    ->formatStateUsing(fn ($state) => $state > 0 ? Jalali::quantity($state) : '—')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),
            ])
            // فیلترهای کامل‌تر — همان‌هایی که «موجودی انبار» دارد
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label(__('stock.warehouse'))
                    ->relationship('warehouse', 'name'),

                SelectFilter::make('category')
                    ->label(__('items.category_label'))
                    ->relationship('itemVersion.item.category', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('in_stock')
                    ->label(__('items.only_in_stock'))
                    ->query(fn (Builder $query) => $query->where('quantity', '>', 0))
                    ->toggle(),

                Filter::make('low_stock')
                    ->label(__('items.only_low_stock'))
                    ->query(fn (Builder $query) => $query->whereHas(
                        'itemVersion',
                        fn (Builder $v) => $v->where('min_stock', '>', 0)
                            ->whereColumn('min_stock', '>=', 'stock_balances.quantity'),
                    ))
                    ->toggle(),

                Filter::make('imported')
                    ->label(__('items.only_imported'))
                    ->query(fn (Builder $query) => $query->whereHas(
                        'itemVersion',
                        fn (Builder $v) => $v->whereNotNull('fx_price')
                            ->where('fx_price', '>', 0)
                            ->where('fx_currency', '!=', 'IRR'),
                    ))
                    ->toggle(),
            ])
            // ویرایش کالا از «موجودی انبار» به اینجا منتقل شد؛ آن صفحه فقط
            // برای دیدن موجودی است.
            ->recordActions([
                Action::make('editItem')
                    ->label(__('stock.edit_item'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn () => auth()->user()?->can(Permission::ManageItems->value) ?? false)
                    ->url(fn (StockBalance $record) => $record->itemVersion?->item
                        ? ItemResource::getUrl('edit', ['record' => $record->itemVersion->item_id])
                        : null),
            ])
            // کالای حذف‌شده (حذف نرم) نباید سطر بی‌نام در فهرست بسازد
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['itemVersion.item.category', 'warehouse'])
                ->whereHas('itemVersion.item'))
            ->defaultSort('quantity', 'desc')
            ->emptyStateHeading(__('stock.empty'));
    }
}
