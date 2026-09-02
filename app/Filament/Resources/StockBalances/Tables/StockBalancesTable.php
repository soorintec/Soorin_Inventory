<?php

namespace App\Filament\Resources\StockBalances\Tables;

use App\Enums\Permission;
use App\Filament\Resources\Items\ItemResource;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
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

                // نامِ انبارِ نگهداری — هر انبار با رنگی متمایز. فقط وقتی فیلترِ
                // انبار روی «همه» است دیده می‌شود؛ با فیلترِ یک انبارِ مشخص، همهٔ
                // سطرها یک انبارند و این ستون تکراری می‌شود، پس پنهان می‌ماند.
                TextColumn::make('warehouse.name')
                    ->label(__('stock.warehouse'))
                    ->badge()
                    ->color(fn (StockBalance $record) => $record->warehouse?->badgeColor() ?? 'gray')
                    ->sortable()
                    ->visible(fn ($livewire) => blank($livewire->getTableFilterState('warehouse_id')['value'] ?? null)),

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

                // انتقالِ موجودیِ این سطر به انبارِ دیگر — کلِ موجودیِ آزادِ همین
                // ورژن در همین انبار جابه‌جا می‌شود (خروج از مبدأ + ورود به مقصد،
                // با حفظِ قیمتِ تمام‌شدهٔ لات؛ هیچ سندی حذف نمی‌شود).
                Action::make('transferItem')
                    ->label(__('stock.transfer_item'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('primary')
                    ->visible(fn () => auth()->user()?->can(Permission::ManageStock->value) ?? false)
                    ->schema(fn (StockBalance $record) => static::transferFormSchema($record->warehouse_id))
                    ->modalHeading(__('stock.transfer_item'))
                    ->action(function (StockBalance $record, array $data) {
                        $moved = static::transferBalances(
                            collect([$record]),
                            (int) $data['to_warehouse_id'],
                            $data['notes'] ?? null,
                        );

                        static::notifyTransfer($moved, (int) $data['to_warehouse_id']);
                    }),

                // حذفِ کالا (نرم، قابل بازیابی) از همین‌جا.
                Action::make('deleteItem')
                    ->label(__('stock.delete_item'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn () => auth()->user()?->can(Permission::ManageItems->value) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading(__('stock.delete_item'))
                    ->modalDescription(__('stock.delete_item_warning'))
                    ->action(function (StockBalance $record) {
                        $record->itemVersion?->item?->delete();
                        Notification::make()->success()->title(__('common.deleted'))->send();
                    }),
            ])
            // تیک‌زدنِ چند کالا و انتقال/حذفِ یک‌جا.
            ->toolbarActions([
                // انتقالِ موجودیِ سطرهای انتخاب‌شده به یک انبارِ مقصد. هر سطر کلِ
                // موجودیِ آزادش را می‌فرستد؛ سطرهایی که همین حالا در انبارِ مقصدند
                // یا موجودیِ آزادی ندارند نادیده گرفته می‌شوند.
                BulkAction::make('transferItems')
                    ->label(__('stock.transfer_selected_items'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('primary')
                    ->visible(fn () => auth()->user()?->can(Permission::ManageStock->value) ?? false)
                    ->schema(static::transferFormSchema())
                    ->modalHeading(__('stock.transfer_selected_items'))
                    ->action(function (Collection $records, array $data) {
                        $moved = static::transferBalances(
                            $records,
                            (int) $data['to_warehouse_id'],
                            $data['notes'] ?? null,
                        );

                        static::notifyTransfer($moved, (int) $data['to_warehouse_id']);
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('deleteItems')
                    ->label(__('stock.delete_selected_items'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn () => auth()->user()?->can(Permission::ManageItems->value) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading(__('stock.delete_selected_items'))
                    ->modalDescription(__('stock.delete_item_warning'))
                    ->action(function (Collection $records) {
                        // هر سطر یک «موجودیِ ورژن» است؛ کالاهای یکتای پشتشان حذف می‌شوند.
                        $itemIds = $records->pluck('itemVersion.item_id')->filter()->unique();
                        $items = Item::whereIn('id', $itemIds)->get();
                        $items->each->delete();

                        Notification::make()->success()
                            ->title(__('stock.deleted_count', ['count' => Jalali::quantity($items->count())]))
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            // کالای حذف‌شده (حذف نرم) نباید سطر بی‌نام در فهرست بسازد
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['itemVersion.item.category', 'warehouse'])
                ->whereHas('itemVersion.item'))
            ->defaultSort('quantity', 'desc')
            // ردیف‌ها یک‌درمیان سفید/خاکستری تا هر سطر از بالا و پایینش جدا دیده
            // شود و کاربر موقع خواندن، سطر را اشتباه نگیرد.
            ->striped()
            ->emptyStateHeading(__('stock.empty'));
    }

    /**
     * فرمِ انتخابِ انبارِ مقصد برای انتقال. اگر مبدأ مشخص باشد (اکشنِ تک‌ردیفی)،
     * همان انبار از فهرستِ مقصد کنار می‌رود تا کاربر آن را انتخاب نکند.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private static function transferFormSchema(?int $excludeWarehouseId = null): array
    {
        return [
            Select::make('to_warehouse_id')
                ->label(__('stock.transfer_target'))
                ->options(fn () => Warehouse::where('is_active', true)
                    ->when($excludeWarehouseId, fn ($q) => $q->whereKeyNot($excludeWarehouseId))
                    ->pluck('name', 'id'))
                ->required()
                ->native(false),

            Textarea::make('notes')->label(__('stock.notes'))->rows(2),
        ];
    }

    /**
     * انتقالِ کلِ موجودیِ آزادِ هر سطر (StockBalance) به انبارِ مقصد.
     *
     * از StockMovementService::transfer استفاده می‌شود تا FIFO و قیمتِ تمام‌شده
     * حفظ شود و سندِ خروج/ورود در کاردکس بماند — مطابقِ قاعدهٔ «هیچ حرکتی حذف
     * نمی‌شود». سطرهایی که در همان انبارِ مقصدند یا موجودیِ آزادشان صفر است رد می‌شوند.
     *
     * @param  Collection<int, StockBalance>  $balances
     * @return int  تعدادِ سطرهای واقعاً منتقل‌شده
     */
    private static function transferBalances(Collection $balances, int $toWarehouseId, ?string $notes): int
    {
        $to = Warehouse::find($toWarehouseId);

        if ($to === null) {
            return 0;
        }

        $service = app(StockMovementService::class);
        $moved = 0;

        foreach ($balances as $balance) {
            $version = $balance->itemVersion;
            $from = $balance->warehouse;
            $quantity = $balance->available();

            // مقصدِ یکسان با مبدأ، یا نبودِ موجودیِ آزاد، یا دادهٔ ناقص → رد شود.
            if ($version === null || $from === null || $from->is($to) || $quantity <= 0) {
                continue;
            }

            try {
                $service->transfer($version, $from, $to, $quantity, $notes);
                $moved++;
            } catch (\RuntimeException|\InvalidArgumentException) {
                // سطرِ خطادار (مثلاً ناسازگاریِ موجودی) از انتقالِ بقیه جلو نگیرد.
                continue;
            }
        }

        return $moved;
    }

    /** پیامِ نتیجهٔ انتقال — موفق با تعداد، یا هشدار وقتی چیزی منتقل نشد. */
    private static function notifyTransfer(int $moved, int $toWarehouseId): void
    {
        if ($moved === 0) {
            Notification::make()->warning()->title(__('stock.transfer_none'))->send();

            return;
        }

        Notification::make()->success()
            ->title(__('stock.transfer_done', [
                'count'     => Jalali::quantity($moved),
                'warehouse' => Warehouse::whereKey($toWarehouseId)->value('name'),
            ]))
            ->send();
    }
}
