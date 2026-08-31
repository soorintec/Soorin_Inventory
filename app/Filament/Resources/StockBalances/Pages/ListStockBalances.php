<?php

namespace App\Filament\Resources\StockBalances\Pages;

use App\Enums\Permission;
use App\Filament\Resources\StockBalances\StockBalanceResource;
use App\Filament\Widgets\RecentActivityWidget;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

/**
 * «مدیریت انبار» — جای تمام کارهای عملیاتی انبار.
 *
 * فرم‌های ورود و خروج عمداً از «کالا» شروع می‌شوند و بعد ورژن می‌آید: کاربر
 * انبار نام کالا را می‌داند، نه شناسه ورژن را. پیش از این فهرست، همه ورژن‌های
 * همه کالاها در یک Select ریخته می‌شد که با ۲۰۰ ورژن غیرقابل استفاده بود.
 */
class ListStockBalances extends ListRecords
{
    protected static string $resource = StockBalanceResource::class;

    public function getTitle(): string
    {
        return __('stock.manage_nav');
    }

    public function getSubheading(): ?string
    {
        return __('stock.manage_intro');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->recordInAction(),
            $this->recordOutAction(),
            $this->transferAction(),
            $this->catalogueGroup(),
            $this->reportsGroup(),
        ];
    }

    /**
     * باز کردن صفحه پیش‌نمایش چاپ در تب جدید مرورگر.
     *
     * چون این اکشن‌ها فرم دارند (انتخاب انبار/بازه)، نمی‌توان از url()+
     * openUrlInNewTab استفاده کرد؛ پس بعد از ثبت فرم با JS تب جدید باز می‌شود.
     */
    private function openPrintTab(string $url): void
    {
        $this->js('window.open(' . json_encode($url) . ", '_blank')");
    }

    /** گزارش‌های چاپی — در تب جدید باز و پنجره چاپ خودکار می‌آید. */
    private function reportsGroup(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('printStock')
                ->label(__('reports.stock_list_title'))
                ->icon(Heroicon::OutlinedPrinter)
                ->schema([
                    Select::make('warehouse')
                        ->label(__('stock.warehouse'))
                        ->options(fn () => Warehouse::pluck('name', 'id'))
                        ->placeholder(__('reports.all_warehouses'))
                        ->native(false),
                    Toggle::make('include_zero')->label(__('reports.include_zero')),
                ])
                // در تب جدید باز شود تا صفحه مدیریت انبار در تب فعلی بماند
                ->action(fn (array $data) => $this->openPrintTab(route('warehouse.print.stock', array_filter([
                    'warehouse'    => $data['warehouse'] ?? null,
                    'include_zero' => ! empty($data['include_zero']) ? 1 : null,
                ])))),

            Action::make('printFlow')
                ->label(__('reports.stock_flow_title'))
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->schema([
                    TextInput::make('from')
                        ->label(__('reports.from_date'))
                        ->placeholder(__('reports.date_from_ph'))
                        ->helperText(__('reports.jalali_hint')),
                    TextInput::make('to')
                        ->label(__('reports.to_date'))
                        ->placeholder(__('reports.date_to_ph'))
                        ->helperText(__('reports.jalali_hint')),
                    Select::make('direction')
                        ->label(__('stock.direction'))
                        ->options(__('stock.directions'))
                        ->placeholder(__('reports.both_directions'))
                        ->native(false),
                    Select::make('warehouse')
                        ->label(__('stock.warehouse'))
                        ->options(fn () => Warehouse::pluck('name', 'id'))
                        ->placeholder(__('reports.all_warehouses'))
                        ->native(false),
                ])
                ->action(fn (array $data) => $this->openPrintTab(route('warehouse.print.flow', array_filter([
                    'from'      => $data['from'] ?? null,
                    'to'        => $data['to'] ?? null,
                    'direction' => $data['direction'] ?? null,
                    'warehouse' => $data['warehouse'] ?? null,
                ])))),

            Action::make('printReorder')
                ->label(__('reports.reorder_title'))
                ->icon(Heroicon::OutlinedShoppingCart)
                ->action(fn () => $this->openPrintTab(route('warehouse.print.reorder'))),

            Action::make('stocktakes')
                ->label(__('stocktake.plural'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->url(fn () => \App\Filament\Resources\Stocktakes\StocktakeResource::getUrl('index')),
        ])
            ->label(__('reports.label'))
            ->icon(Heroicon::OutlinedDocumentChartBar)
            ->button()
            ->color('gray');
    }

    private function canManageStock(): bool
    {
        return auth()->user()?->can(Permission::ManageStock->value) ?? false;
    }

    private function canManageItems(): bool
    {
        return auth()->user()?->can(Permission::ManageItems->value) ?? false;
    }

    /**
     * فیلدهای مشترک ورود و خروج، به همان ترتیبی که کاربر فکر می‌کند:
     * کالا ← ورژن ← انبار ← تعداد ← (…) ← توضیحات.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private function itemAndVersionFields(bool $allowCreate = false): array
    {
        $itemSelect = Select::make('item_id')
            ->label(__('stock.item'))
            ->helperText(__('stock.item_hint'))
            ->options(fn () => Item::query()->orderBy('name')->pluck('name', 'id'))
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->afterStateUpdated(function ($state, $set) {
                // اگر کالا فقط یک ورژن دارد، انتخابش را از کاربر نپرسیم
                $versions = ItemVersion::where('item_id', $state)->pluck('id');
                $set('item_version_id', $versions->count() === 1 ? $versions->first() : null);
            });

        // «تعریف کالای جدید» درجا — فقط در «ورود کالا» و برای کسی که مجوزِ مدیریتِ
        // کالا دارد. اگر کالا هنوز ثبت نشده، بدونِ ترکِ این فرم ساخته می‌شود
        // (کالا + در صورت نیاز دستهٔ نو + ورژنِ اول). خواسته: «همین‌جا دکمهٔ تعریف کالا».
        if ($allowCreate) {
            $itemSelect
                ->createOptionForm([
                    TextInput::make('name')->label(__('items.name'))->required()->maxLength(255),
                    TextInput::make('code')->label(__('items.code'))->required()->maxLength(30)->unique('items', 'code'),
                    Select::make('item_category_id')
                        ->label(__('items.category_label'))
                        ->options(fn () => ItemCategory::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->createOptionForm([TextInput::make('name')->label(__('items.category_label'))->required()])
                        ->createOptionUsing(fn (array $data) => ItemCategory::create(['name' => $data['name']])->id),
                    TextInput::make('unit')->label(__('items.unit'))->default(__('items.unit_default'))->required(),
                    TextInput::make('version_code')->label(__('items.version_label'))->default(__('items.default_version'))->required(),
                ])
                ->createOptionUsing(function (array $data): int {
                    $item = Item::create([
                        'item_category_id' => $data['item_category_id'],
                        'code'             => $data['code'],
                        'name'             => $data['name'],
                        'unit'             => $data['unit'] ?? __('items.unit_default'),
                    ]);
                    $item->versions()->create(['version_code' => $data['version_code']]);

                    return $item->id;
                });
        }

        return [
            $itemSelect,

            Select::make('item_version_id')
                ->label(__('items.version_label'))
                ->helperText(__('stock.version_hint'))
                ->options(fn ($get) => $get('item_id')
                    ? ItemVersion::where('item_id', $get('item_id'))
                        ->orderBy('version_code')
                        ->pluck('version_code', 'id')
                    : [])
                ->searchable()
                ->required()
                ->native(false)
                ->live()
                ->placeholder(fn ($get) => $get('item_id') && ItemVersion::where('item_id', $get('item_id'))->doesntExist()
                    ? __('stock.no_version')
                    : null),

            Select::make('warehouse_id')
                ->label(__('stock.warehouse'))
                ->options(fn () => Warehouse::where('is_active', true)->pluck('name', 'id'))
                ->default(fn () => Warehouse::where('code', 'MAIN')->value('id'))
                ->required()
                ->native(false)
                ->live(),

            // موجودی فعلیِ کالای انتخاب‌شده (در انبارِ انتخاب‌شده) — تا کاربر بداند چقدر دارد.
            Placeholder::make('current_stock')
                ->label(__('items.current_stock'))
                ->content(function ($get) {
                    $vid = $get('item_version_id');
                    $version = $vid ? ItemVersion::find($vid) : null;

                    if (! $version) {
                        return '—';
                    }

                    $wid = $get('warehouse_id');
                    $qty = $wid
                        ? (float) $version->balances()->where('warehouse_id', $wid)->sum('quantity')
                        : $version->totalQuantity();

                    return \App\Support\Jalali::quantity($qty) . ' ' . ($version->item->unit ?? '');
                }),

            TextInput::make('quantity')
                ->label(__('stock.quantity'))
                ->helperText(__('stock.quantity_hint'))
                ->numeric()
                ->required()
                // فلش بالا/پایین برای کالای «عدد» باید یک‌واحدی جلو برود، نه
                // اعشاری. برای کالای متر/کیلوگرم اعشار مجاز می‌ماند.
                //
                // min هم باید با step هم‌تراز باشد: با min=۰٫۰۱ و step=۱، مقادیر
                // معتبرِ HTML می‌شد ۰٫۰۱، ۱٫۰۱، ۲٫۰۱ و ورود «۱» رد می‌شد. حالا
                // برای کالای عدد، min و step هر دو ۱ است، پس «۱» پذیرفته می‌شود.
                ->step(fn ($get) => $this->quantityStep($get('item_id')))
                ->minValue(fn ($get) => $this->quantityStep($get('item_id'))),
        ];
    }

    /** گام ورودی تعداد: کالای شمردنی صحیح، کالای پیوسته اعشاری. */
    private function quantityStep(mixed $itemId): int|float
    {
        $unit = $itemId ? Item::whereKey($itemId)->value('unit') : 'عدد';

        return $unit === 'عدد' ? 1 : 0.01;
    }

    /**
     * قیمت تمام‌شده لات از قیمت خود ورژن می‌آید — فقط وقتی ریالی باشد.
     *
     * برای قطعه ارزی (دلار/یوان) قیمت ریالیِ لات صفر می‌ماند، چون بدون نرخ
     * تبدیل نمی‌توان آن را ریالی کرد؛ ارزش ارزی‌اش روی خود ورژن نگه داشته
     * می‌شود و در داشبورد و گزارش به تفکیک ارز دیده می‌شود.
     */
    private function lotCostFor(ItemVersion $version): int
    {
        if ($version->fx_currency === 'IRR' && $version->hasPrice()) {
            return (int) round((float) $version->fx_price);
        }

        return 0;
    }

    private function recordInAction(): Action
    {
        return Action::make('recordIn')
            ->label(__('stock.record_in'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->visible(fn () => $this->canManageStock())
            ->schema([
                ...$this->itemAndVersionFields($this->canManageItems()),

                // قیمت دیگر «ریالیِ پنهان» نیست: همان قیمتِ خود قطعه است با ارز
                // انتخابی. اگر پر شود، قیمت روی ورژن به‌روز می‌شود (یک قیمت واحد
                // برای هر قطعه) و همه‌جا — موجودی، گزارش، داشبورد — دیده می‌شود.
                // خالی یعنی قیمت قبلی ورژن دست‌نخورده می‌ماند.
                TextInput::make('fx_price')
                    ->label(__('stock.price'))
                    ->helperText(__('stock.price_hint'))
                    ->numeric()
                    ->minValue(0),

                Select::make('fx_currency')
                    ->label(__('items.fx_currency'))
                    ->options(\App\Models\Currency::options())
                    ->default('IRR')
                    ->selectablePlaceholder(false)
                    ->native(false),

                Textarea::make('notes')->label(__('stock.notes'))->rows(2)->columnSpanFull(),
            ])
            ->action(function (array $data) {
                $version = ItemVersion::findOrFail($data['item_version_id']);
                $note = filled($data['notes'] ?? null) ? trim($data['notes']) : null;

                // قیمت واردشده روی خود ورژن می‌نشیند — یک قیمت واحد برای قطعه
                if (filled($data['fx_price'] ?? null)) {
                    $version->update([
                        'fx_price'    => $data['fx_price'],
                        'fx_currency' => $data['fx_currency'] ?? 'IRR',
                    ]);
                }

                app(StockMovementService::class)->recordIn(
                    $version,
                    Warehouse::findOrFail($data['warehouse_id']),
                    (float) $data['quantity'],
                    $this->lotCostFor($version),
                    StockMovement::REASON_INITIAL,
                    notes: $note,
                );

                // توضیح ورود به یادداشت خود ورژن هم اضافه می‌شود — با یک اسلش از
                // یادداشت قبلی جدا، تا سابقه هر ورود کنار هم بماند و پاک نشود.
                if ($note !== null) {
                    $version->notes = filled($version->notes) ? $version->notes . ' / ' . $note : $note;
                    $version->save();
                }

                Notification::make()->success()->title(__('common.saved'))->send();
            });
    }

    private function recordOutAction(): Action
    {
        return Action::make('recordOut')
            ->label(__('stock.record_out'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('danger')
            ->visible(fn () => $this->canManageStock())
            ->schema([
                ...$this->itemAndVersionFields(),

                Select::make('reason')
                    ->label(__('stock.reason'))
                    ->options(collect(__('stock.reasons'))->except(['purchase', 'initial'])->all())
                    ->default('adjustment')
                    ->required()
                    ->native(false),

                Textarea::make('notes')->label(__('stock.notes'))->rows(2)->columnSpanFull(),
            ])
            ->action(function (array $data) {
                try {
                    app(StockMovementService::class)->recordOut(
                        ItemVersion::findOrFail($data['item_version_id']),
                        Warehouse::findOrFail($data['warehouse_id']),
                        (float) $data['quantity'],
                        $data['reason'],
                        notes: $data['notes'] ?? null,
                    );

                    Notification::make()->success()->title(__('common.saved'))->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title(__('stock.insufficient_stock'))->body($e->getMessage())->send();
                }
            });
    }

    private function transferAction(): Action
    {
        return Action::make('transfer')
            ->label(__('stock.record_transfer'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn () => $this->canManageStock())
            ->schema([
                Select::make('item_id')
                    ->label(__('stock.item'))
                    ->helperText(__('stock.item_hint'))
                    ->options(fn () => Item::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        $versions = ItemVersion::where('item_id', $state)->pluck('id');
                        $set('item_version_id', $versions->count() === 1 ? $versions->first() : null);
                    }),

                Select::make('item_version_id')
                    ->label(__('items.version_label'))
                    ->options(fn ($get) => $get('item_id')
                        ? ItemVersion::where('item_id', $get('item_id'))->orderBy('version_code')->pluck('version_code', 'id')
                        : [])
                    ->searchable()
                    ->required()
                    ->native(false),

                Select::make('from_warehouse_id')
                    ->label(__('stock.from_warehouse'))
                    ->options(fn () => Warehouse::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->native(false),

                Select::make('to_warehouse_id')
                    ->label(__('stock.to_warehouse'))
                    ->options(fn () => Warehouse::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->native(false),

                TextInput::make('quantity')->label(__('stock.quantity'))->numeric()->required()
                    ->step(fn ($get) => $this->quantityStep($get('item_id')))
                    ->minValue(fn ($get) => $this->quantityStep($get('item_id'))),

                Textarea::make('notes')->label(__('stock.notes'))->rows(2)->columnSpanFull(),
            ])
            ->action(function (array $data) {
                try {
                    app(StockMovementService::class)->transfer(
                        ItemVersion::findOrFail($data['item_version_id']),
                        Warehouse::findOrFail($data['from_warehouse_id']),
                        Warehouse::findOrFail($data['to_warehouse_id']),
                        (float) $data['quantity'],
                        notes: $data['notes'] ?? null,
                    );

                    Notification::make()->success()->title(__('common.saved'))->send();
                } catch (RuntimeException|\InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    /** ساخت کالا، دسته و انبار — کارهایی که قبلاً بخش جدا در منو داشتند. */
    private function catalogueGroup(): ActionGroup
    {
        return ActionGroup::make([
            $this->newItemAction(),
            // «دسته‌بندی جدید» از اینجا برداشته شد؛ ساخت/ویرایش/حذفِ دسته از خودِ
            // صفحهٔ «دسته‌بندی‌ها» انجام می‌شود (خواسته: تکراری نباشد).

            // «ویرایش کالاها» عمداً حذف شد — کالاها از منوی «کالاها» ویرایش
            // می‌شوند و این میان‌بر فقط شلوغی بود.

            // مدیریت دسته‌بندی‌ها (ویرایش نام و حذف) — صفحه‌اش از قبل هست ولی در منو
            // نبود، پس کاربر راهی برای حذف دسته نداشت.
            Action::make('manageCategories')
                ->label(\App\Filament\Resources\ItemCategories\ItemCategoryResource::getPluralModelLabel())
                ->icon(Heroicon::OutlinedTag)
                ->url(fn () => \App\Filament\Resources\ItemCategories\ItemCategoryResource::getUrl('index')),

            Action::make('manageWarehouses')
                ->label(__('warehouses.plural'))
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->url(fn () => \App\Filament\Resources\Warehouses\WarehouseResource::getUrl('index')),
        ])
            ->label(__('stock.catalogue'))
            ->icon(Heroicon::OutlinedSquares2x2)
            ->button()
            ->visible(fn () => $this->canManageItems());
    }

    private function newItemAction(): Action
    {
        return Action::make('newItem')
            ->label(__('stock.new_item'))
            ->icon(Heroicon::OutlinedCube)
            ->schema([
                Select::make('item_category_id')
                    ->label(__('items.category_label'))
                    ->options(fn () => ItemCategory::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->native(false),

                TextInput::make('name')->label(__('items.name'))->required()->maxLength(255),
                TextInput::make('code')->label(__('items.code'))->required()->maxLength(30)->unique('items', 'code'),
                TextInput::make('unit')->label(__('items.unit'))->default(__('items.unit_default'))->required()->maxLength(20),
                TextInput::make('brand')->label(__('items.brand'))->maxLength(60),

                // کالای تازه بدون ورژن به درد نمی‌خورد؛ ورژن اول همین‌جا ساخته می‌شود
                TextInput::make('version_code')
                    ->label(__('items.version_code'))
                    ->helperText(__('stock.first_version_hint'))
                    ->default('اصلی')
                    ->required()
                    ->maxLength(40),

                TextInput::make('location')->label(__('items.location'))->maxLength(60),

                // قیمت همین ابتدا پرسیده می‌شود تا کالای تازه بدون قیمت نماند
                TextInput::make('fx_price')
                    ->label(__('items.fx_price'))
                    ->helperText(__('items.fx_price_hint'))
                    ->numeric()
                    ->minValue(0),

                Select::make('fx_currency')
                    ->label(__('items.fx_currency'))
                    ->options(\App\Models\Currency::options())
                    ->default('IRR')
                    ->selectablePlaceholder(false)
                    ->native(false),

                Toggle::make('track_serial')
                    ->label(__('items.track_serial'))
                    ->helperText(__('items.track_serial_hint')),

                Textarea::make('description')->label(__('items.description'))->rows(2)->columnSpanFull(),
            ])
            ->action(function (array $data) {
                $item = Item::create([
                    'item_category_id' => $data['item_category_id'],
                    'name'             => $data['name'],
                    'code'             => $data['code'],
                    'unit'             => $data['unit'],
                    'brand'            => $data['brand'] ?? null,
                    'track_serial'     => $data['track_serial'] ?? false,
                    'description'      => $data['description'] ?? null,
                ]);

                $item->versions()->create([
                    'version_code' => $data['version_code'],
                    'location'     => $data['location'] ?? null,
                    'fx_price'     => filled($data['fx_price'] ?? null) ? $data['fx_price'] : null,
                    'fx_currency'  => $data['fx_currency'] ?? 'IRR',
                ]);

                Notification::make()
                    ->success()
                    ->title(__('stock.item_created', ['name' => $item->name]))
                    ->body(__('stock.item_created_hint'))
                    ->send();
            });
    }

    private function newCategoryAction(): Action
    {
        return Action::make('newCategory')
            ->label(__('stock.new_category'))
            ->icon(Heroicon::OutlinedTag)
            ->schema([
                TextInput::make('name')->label(__('items.category_label'))->required()->maxLength(100),
                TextInput::make('code')->label(__('items.code'))->maxLength(20),
            ])
            ->action(function (array $data) {
                ItemCategory::create(['name' => $data['name'], 'code' => $data['code'] ?? null]);

                Notification::make()->success()->title(__('common.saved'))->send();
            });
    }
}
