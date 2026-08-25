<?php

namespace App\Filament\Resources\Suppliers;

use App\Enums\Permission;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Supplier;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('purchasing.supplier_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchasing.supplier_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('purchasing.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('common.name'))->required()->maxLength(255),
            TextInput::make('country')->label(__('purchasing.country'))->maxLength(60),
            TextInput::make('phone')->label(__('common.phone'))->maxLength(30),
            TextInput::make('email')->label(__('common.email'))->email()->maxLength(255),
            Textarea::make('address')->label(__('common.address'))->rows(2)->columnSpanFull(),
            Textarea::make('notes')->label(__('common.notes'))->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('common.name'))->searchable()->weight('medium'),
                TextColumn::make('country')->label(__('purchasing.country'))->placeholder('—'),
                TextColumn::make('phone')->label(__('common.phone'))->placeholder('—'),
                TextColumn::make('purchases_count')->label(__('purchasing.plural'))->counts('purchases')->badge(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('common.empty_state'));
    }

    public static function getPages(): array
    {
        return ['index' => ListSuppliers::route('/')];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewPurchases->value) ?? false;
    }
}
