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
use Filament\Forms\Components\Toggle;
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
                            (bool) ($data['remove_from_source'] ?? false),
                        );

                        static::notifyTransfer($moved, (int) $data['to_warehouse_id']);
                    }),

                // حذفِ کالا — انبار-محور: فقط از همین انبار برداشته می‌شود؛ اگر کالا
                // در انبارِ دیگری هم موجودی داشته باشد، آن‌جا دست‌نخورده می‌ماند و فقط
                // وقتی در هیچ انباری نماند، خودِ کالا حذفِ نرم می‌شود.
                Action::make('deleteItem')
                    ->label(__('stock.delete_item'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn () => auth()->user()?->can(Permission::ManageItems->value) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading(__('stock.delete_item'))
                    ->modalDescription(fn (StockBalance $record) => static::deleteWarning($record))
                    ->action(function (StockBalance $record) {
                        $item = $record->itemVersion?->item;

                        if ($item !== null) {
                            static::removeItemFromWarehouse($item, $record->warehouse_id);
                        }

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
                            (bool) ($data['remove_from_source'] ?? false),
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
                    ->modalDescription(__('stock.delete_selected_warning'))
                    ->action(function (Collection $records) {
                        // هر سطر انبار-محور حذف می‌شود: کالا فقط از انبارِ همان ردیف
                        // برداشته می‌شود و اگر در هیچ انباری نماند، کاملاً حذفِ نرم می‌شود.
                        $count = 0;

                        foreach ($records as $record) {
                            $item = $record->itemVersion?->item;

                            if ($item === null) {
                                continue;
                            }

                            static::removeItemFromWarehouse($item, $record->warehouse_id);
                            $count++;
                        }

                        Notification::make()->success()
                            ->title(__('stock.remove_done', ['count' => Jalali::quantity($count)]))
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

            // پس از انتقال، یا فقط موجودی رفته و ردیفِ خالیِ مبدأ می‌ماند، یا نامِ
            // کالا هم از مبدأ برداشته می‌شود — انتخابِ کاربر.
            Toggle::make('remove_from_source')
                ->label(__('stock.transfer_remove_source'))
                ->helperText(__('stock.transfer_remove_source_hint'))
                ->default(false),

            Textarea::make('notes')->label(__('stock.notes'))->rows(2),
        ];
    }

    /**
     * حذفِ انبار-محور — قلبِ رفعِ باگ: حذف از یک انبار نباید انبارهای دیگر را ببرد.
     *
     * - اگر کالا در انبارِ دیگری هم موجودی دارد: فقط از همین انبار برداشته می‌شود
     *   (ردیفِ مانده پاک، و موجودیِ آزادِ باقی‌مانده به‌صورت «اصلاح» خارج می‌شود).
     * - اگر این تنها انبارِ کالاست: مثلِ قبل کلِ کالا حذفِ نرم می‌شود و موجودی/لات‌ها
     *   دست‌نخورده به‌عنوان سابقه می‌مانند (تا بازیابیِ کالا موجودی را هم برگرداند).
     */
    private static function removeItemFromWarehouse(Item $item, int $warehouseId): void
    {
        $versionIds = $item->versions()->pluck('id');

        $inOtherWarehouses = StockBalance::whereIn('item_version_id', $versionIds)
            ->where('warehouse_id', '!=', $warehouseId)
            ->exists();

        if (! $inOtherWarehouses) {
            // تنها انبارِ کالا → رفتارِ قبلی حفظ می‌شود.
            $item->delete();

            return;
        }

        $warehouse = Warehouse::find($warehouseId);

        if ($warehouse === null) {
            return;
        }

        $service = app(StockMovementService::class);

        foreach ($item->versions()->get() as $version) {
            $service->removeFromWarehouse($version, $warehouse);
        }
    }

    /**
     * متنِ هشدارِ حذف بسته به اینکه کالا در انبارِ دیگری هم هست یا نه — تا کاربر
     * بداند حذف فقط از همین انبار است یا کلِ کالا را می‌برد.
     */
    private static function deleteWarning(StockBalance $record): string
    {
        $item = $record->itemVersion?->item;

        if ($item === null) {
            return __('stock.delete_item_warning');
        }

        $inOtherWarehouses = StockBalance::whereIn('item_version_id', $item->versions()->pluck('id'))
            ->where('warehouse_id', '!=', $record->warehouse_id)
            ->exists();

        return $inOtherWarehouses
            ? __('stock.delete_item_from_warehouse_warning', ['warehouse' => $record->warehouse?->name])
            : __('stock.delete_item_warning');
    }

    /**
     * انتقالِ کلِ موجودیِ آزادِ هر سطر (StockBalance) به انبارِ مقصد.
     *
     * از StockMovementService::transfer استفاده می‌شود تا FIFO و قیمتِ تمام‌شده
     * حفظ شود و سندِ خروج/ورود در کاردکس بماند — مطابقِ قاعدهٔ «هیچ حرکتی حذف
     * نمی‌شود». سطرهایی که در همان انبارِ مقصدند یا موجودیِ آزادشان صفر است رد می‌شوند.
     *
     * اگر $removeFromSource روشن باشد، پس از انتقال، ردیفِ (اکنون خالیِ) مبدأ هم
     * برداشته می‌شود تا نامِ کالا زیرِ انبارِ مبدأ نماند؛ وگرنه فقط موجودی می‌رود.
     *
     * @param  Collection<int, StockBalance>  $balances
     * @return int  تعدادِ سطرهای واقعاً منتقل‌شده
     */
    private static function transferBalances(Collection $balances, int $toWarehouseId, ?string $notes, bool $removeFromSource = false): int
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

                if ($removeFromSource) {
                    // موجودیِ مبدأ حالا صفر است؛ این فقط ردیفِ خالی را برمی‌دارد.
                    $service->removeFromWarehouse($version, $from, $notes);
                }
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
