<?php

namespace App\Filament\Resources\StockMovements\Tables;

use App\Filament\Resources\Items\Pages\ItemKardex;
use App\Models\StockMovement;
use App\Support\Jalali;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('common.date'))
                    ->formatStateUsing(fn ($state) => Jalali::formatDateTime($state))
                    ->sortable(),

                TextColumn::make('itemVersion.item.name')->label(__('items.label'))->searchable(),
                TextColumn::make('itemVersion.version_code')->label(__('items.version_label')),
                TextColumn::make('warehouse.name')->label(__('stock.warehouse'))->badge()->color('gray'),

                TextColumn::make('direction')
                    ->label(__('stock.direction'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("stock.directions.$state"))
                    ->color(fn (string $state) => $state === 'in' ? 'success' : 'danger'),

                TextColumn::make('reason')
                    ->label(__('stock.reason'))
                    ->formatStateUsing(fn (string $state) => __("stock.reasons.$state")),

                TextColumn::make('quantity')
                    ->label(__('stock.quantity'))
                    ->formatStateUsing(fn ($state) => Jalali::quantity($state)),

                TextColumn::make('unit_cost')
                    ->label(__('stock.unit_cost'))
                    ->formatStateUsing(fn ($state) => Jalali::money($state))
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),

                TextColumn::make('user.name')
                    ->label(__('stock.user'))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('direction')->label(__('stock.direction'))->options(__('stock.directions')),
                SelectFilter::make('reason')->label(__('stock.reason'))->options(__('stock.reasons')),
                SelectFilter::make('warehouse_id')->label(__('stock.warehouse'))->relationship('warehouse', 'name'),
                SelectFilter::make('user_id')->label(__('stock.user'))->relationship('user', 'name'),
            ])
            // کلیک روی هر تراکنش → کاردکسِ همان کالا.
            ->recordUrl(fn (StockMovement $record) => $record->itemVersion?->item_id
                ? ItemKardex::getUrl(['record' => $record->itemVersion->item_id])
                : null)
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('stock.empty'));
    }
}
