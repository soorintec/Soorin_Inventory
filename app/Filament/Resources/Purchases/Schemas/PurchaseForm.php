<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Currency;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Forms\Components\DatePicker;
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

            Textarea::make('notes')->label(__('common.notes'))->rows(2)->columnSpanFull(),
        ]);
    }
}
