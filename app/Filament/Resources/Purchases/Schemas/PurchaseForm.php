<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Currency;
use App\Models\ItemVersion;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('purchasing.label'))
                ->columns(3)
                ->schema([
                    Select::make('supplier_id')
                        ->label(__('purchasing.supplier_label'))
                        ->options(fn () => Supplier::pluck('name', 'id'))
                        ->searchable()
                        ->native(false),

                    Select::make('warehouse_id')
                        ->label(__('purchasing.warehouse'))
                        ->options(fn () => Warehouse::pluck('name', 'id'))
                        ->required()
                        ->native(false),

                    Select::make('type')
                        ->label(__('purchasing.type'))
                        ->options(__('purchasing.types'))
                        ->default('import')
                        ->required()
                        ->native(false),

                    DatePicker::make('order_date')->label(__('purchasing.order_date'))->default(now())->required(),
                    DatePicker::make('received_date')->label(__('purchasing.received_date')),
                ]),

            Section::make(__('purchasing.currency_section'))
                ->columns(3)
                ->schema([
                    Select::make('currency_id')
                        ->label(__('purchasing.currency_label'))
                        ->options(fn () => Currency::pluck('code', 'id'))
                        ->native(false),

                    TextInput::make('fx_amount')->label(__('purchasing.fx_amount'))->numeric()->default(0),
                    DatePicker::make('transfer_date')->label(__('purchasing.transfer_date')),

                    TextInput::make('rate_to_irr')
                        ->label(__('purchasing.rate_to_irr'))
                        ->numeric()
                        ->required()
                        // نرخ صفر بی‌سروصدا ارزش کل کالا را صفر می‌کند
                        ->minValue(1)
                        ->suffix(__('common.currency')),

                    TextInput::make('usd_rate_irr')->label(__('purchasing.usd_rate_irr'))->numeric()->default(0)->suffix(__('common.currency')),
                ]),

            Section::make(__('purchasing.costs_section'))
                ->columns(3)
                ->collapsible()
                ->schema([
                    TextInput::make('shipping_cost')->label(__('purchasing.shipping_cost'))->numeric()->default(0)->suffix(__('common.currency')),
                    TextInput::make('customs_cost')->label(__('purchasing.customs_cost'))->numeric()->default(0)->suffix(__('common.currency')),
                    TextInput::make('clearance_cost')->label(__('purchasing.clearance_cost'))->numeric()->default(0)->suffix(__('common.currency')),
                    TextInput::make('insurance_cost')->label(__('purchasing.insurance_cost'))->numeric()->default(0)->suffix(__('common.currency')),
                    TextInput::make('other_cost')->label(__('purchasing.other_cost'))->numeric()->default(0)->suffix(__('common.currency')),

                    Select::make('allocation_method')
                        ->label(__('purchasing.allocation_method'))
                        ->options(__('purchasing.allocation_methods'))
                        ->default('value')
                        ->required()
                        ->native(false),
                ]),

            // ردیف‌های کالا همین‌جا (هنگام ساخت و ویرایش) انتخاب می‌شوند — پیش‌تر
            // فقط در صفحهٔ ویرایش و به‌صورت جدولِ رابطه بود، برای همین موقع «ساختِ
            // خرید» جایی برای انتخاب کالا دیده نمی‌شد. قیمت تمام‌شده هنگام «دریافت»
            // خودکار محاسبه می‌شود؛ اینجا فقط کالا، تعداد، قیمت ارزی و وزن.
            Section::make(__('purchasing.items'))
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel(__('purchasing.add_item'))
                        ->columns(4)
                        ->defaultItems(1)
                        ->schema([
                            Select::make('item_version_id')
                                ->label(__('purchasing.item_version'))
                                ->options(fn () => ItemVersion::with('item')->get()
                                    ->mapWithKeys(fn (ItemVersion $v) => [$v->id => $v->displayName()]))
                                ->searchable()
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('quantity')->label(__('purchasing.quantity'))->numeric()->required()->minValue(0.01),
                            TextInput::make('fx_unit_price')->label(__('purchasing.fx_unit_price'))->numeric()->required(),
                            TextInput::make('weight_kg')->label(__('purchasing.weight_kg'))->numeric()->columnSpan(2),
                        ]),
                ]),

            Textarea::make('notes')->label(__('common.notes'))->rows(2)->columnSpanFull(),
        ]);
    }
}
