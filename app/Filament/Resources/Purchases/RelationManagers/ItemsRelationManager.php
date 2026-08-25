<?php

namespace App\Filament\Resources\Purchases\RelationManagers;

use App\Models\ItemVersion;
use App\Models\Purchase;
use App\Support\Jalali;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ردیف‌های سند خرید. قیمت تمام‌شده (unit_price_irr / allocated_cost /
 * landed_unit_cost) اینجا وارد نمی‌شود — هنگام «دریافت» خودکار محاسبه
 * می‌شود. تا آن زمان فقط تعداد و قیمت ارزی و وزن ثبت می‌شود.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('purchasing.items');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('item_version_id')
                ->label(__('purchasing.item_version'))
                ->options(fn () => ItemVersion::with('item')->get()->mapWithKeys(fn (ItemVersion $v) => [$v->id => $v->displayName()]))
                ->searchable()
                ->required(),

            TextInput::make('quantity')->label(__('purchasing.quantity'))->numeric()->required()->minValue(0.01),
            TextInput::make('fx_unit_price')->label(__('purchasing.fx_unit_price'))->numeric()->required(),
            TextInput::make('weight_kg')->label(__('purchasing.weight_kg'))->numeric(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        /** @var Purchase $purchase */
        $purchase = $this->getOwnerRecord();
        $received = $purchase->status === Purchase::STATUS_RECEIVED;

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('itemVersion.item.name')->label(__('items.label'))->weight('medium'),
                TextColumn::make('itemVersion.version_code')->label(__('items.version_label')),
                TextColumn::make('quantity')->label(__('purchasing.quantity'))->formatStateUsing(fn ($state) => Jalali::quantity($state)),
                TextColumn::make('fx_unit_price')->label(__('purchasing.fx_unit_price'))->formatStateUsing(fn ($state) => Jalali::digits((string) $state)),
                TextColumn::make('landed_unit_cost')
                    ->label(__('purchasing.landed_unit_cost'))
                    ->formatStateUsing(fn ($state) => $state > 0 ? Jalali::money($state) : '—')
                    ->weight('bold'),
            ])
            ->headerActions([
                CreateAction::make()->disabled($received),
            ])
            ->recordActions([
                EditAction::make()->visible(! $received),
                DeleteAction::make()->visible(! $received),
            ])
            ->emptyStateHeading(__('purchasing.no_items'));
    }
}
