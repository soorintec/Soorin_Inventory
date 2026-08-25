<?php

namespace App\Filament\Resources\CustomerSystems\RelationManagers;

use App\Models\CustomerSystem;
use App\Models\CustomerSystemPart;
use App\Models\ItemVersion;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use App\Support\Jalali;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * قطعات واقعی نصب‌شده در سامانه اجراشده.
 *
 * افزودن قطعه = خروج FIFO از انبار انتخابی. قیمت تمام‌شده هر واحد از
 * لات FIFO همان لحظه گرفته و روی قطعه **منجمد** می‌شود — تغییر قیمت
 * بعدی کالا این را عوض نمی‌کند. مبنای گزارش «این سامانه چقدر تمام شد».
 */
class PartsRelationManager extends RelationManager
{
    protected static string $relationship = 'parts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('systems.parts');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('item_version_id')
                ->label(__('systems.part_version'))
                ->options(fn () => ItemVersion::with('item')->get()->mapWithKeys(fn (ItemVersion $v) => [$v->id => $v->displayName()]))
                ->searchable()
                ->required(),

            Select::make('warehouse_id')
                ->label(__('stock.from_warehouse'))
                ->options(fn () => Warehouse::pluck('name', 'id'))
                ->required()
                ->native(false)
                ->dehydrated(false),   // فقط برای خروج انبار؛ روی قطعه ذخیره نمی‌شود

            TextInput::make('quantity')->label(__('systems.part_quantity'))->numeric()->default(1)->required()->minValue(0.01),

            TextInput::make('replaced_by_ticket_number')
                ->label(__('systems.replaced_ticket'))
                ->maxLength(20),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('itemVersion.item.name')->label(__('items.label'))->weight('medium'),
                TextColumn::make('itemVersion.version_code')->label(__('items.version_label')),
                TextColumn::make('quantity')->label(__('systems.part_quantity'))->formatStateUsing(fn ($s) => Jalali::quantity($s)),
                TextColumn::make('unit_cost')->label(__('systems.part_unit_cost'))->formatStateUsing(fn ($s) => Jalali::money($s)),
                TextColumn::make('replaced_by_ticket_number')->label(__('systems.replaced_ticket'))->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): CustomerSystemPart {
                        /** @var CustomerSystem $system */
                        $system = $this->getOwnerRecord();
                        $version = ItemVersion::findOrFail($data['item_version_id']);
                        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
                        $qty = (float) $data['quantity'];

                        // خروج FIFO از انبار — ممکن است چند لات با قیمت‌های مختلف را پوشش دهد
                        $movements = app(StockMovementService::class)->recordOut(
                            $version, $warehouse, $qty, StockMovement::REASON_PROJECT,
                            referenceType: CustomerSystem::class, referenceId: $system->id,
                        );

                        // قیمت تمام‌شده میانگین وزنی لات‌های مصرف‌شده (منجمد)
                        $totalCost = array_sum(array_map(fn ($m) => (float) $m->quantity * $m->unit_cost, $movements));
                        $unitCost = (int) round($totalCost / $qty);

                        $part = $system->parts()->create([
                            'item_version_id'           => $version->id,
                            'quantity'                  => $qty,
                            'unit_cost'                 => $unitCost,
                            'installed_at'              => now(),
                            'replaced_by_ticket_number' => $data['replaced_by_ticket_number'] ?? null,
                        ]);

                        $system->recalculateTotalCost();

                        return $part;
                    })
                    ->before(function (array $data, CreateAction $action) {
                        // پیش‌بررسی موجودی برای پیام خطای تمیز به‌جای استثنا وسط ساخت
                        $version = ItemVersion::find($data['item_version_id']);
                        $balance = \App\Models\StockBalance::where('item_version_id', $data['item_version_id'])
                            ->where('warehouse_id', $data['warehouse_id'])->first();

                        if (! $balance || $balance->available() < (float) $data['quantity']) {
                            Notification::make()->danger()->title(__('stock.insufficient_stock'))->send();
                            $action->halt();
                        }
                    }),
            ])
            ->recordActions([DeleteAction::make()])
            ->emptyStateHeading(__('systems.parts_empty'));
    }
}
