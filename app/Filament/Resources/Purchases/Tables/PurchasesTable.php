<?php

namespace App\Filament\Resources\Purchases\Tables;

use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Purchase;
use App\Support\Jalali;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchasesTable
{
    private const STATUS_COLORS = [
        'draft' => 'gray', 'ordered' => 'info', 'received' => 'success', 'cancelled' => 'danger',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Purchase $record) => PurchaseResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('number')->label(__('purchasing.number'))->fontFamily('mono')->searchable(),
                TextColumn::make('supplier.name')->label(__('purchasing.supplier_label'))->placeholder('—')->searchable(),
                TextColumn::make('warehouse.name')->label(__('purchasing.warehouse'))->badge()->color('gray'),
                TextColumn::make('order_date')
                    ->label(__('purchasing.order_date'))
                    ->formatStateUsing(fn ($state) => Jalali::format($state))
                    ->sortable(),
                TextColumn::make('total_cost_irr')
                    ->label(__('purchasing.total_cost'))
                    ->formatStateUsing(fn ($state) => $state > 0 ? Jalali::money($state) : '—')
                    ->extraHeaderAttributes(['class' => 'hidden lg:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden lg:table-cell']),
                TextColumn::make('status')
                    ->label(__('purchasing.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("purchasing.statuses.$state"))
                    ->color(fn (string $state) => self::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('purchasing.status'))->options(__('purchasing.statuses')),
            ])
            ->defaultSort('order_date', 'desc')
            ->emptyStateHeading(__('purchasing.empty'));
    }
}
