<?php

namespace App\Filament\Resources\Currencies;

use App\Enums\Permission;
use App\Filament\Resources\Currencies\Pages\ListCurrencies;
use App\Models\Currency;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 21;

    public static function getModelLabel(): string
    {
        return __('purchasing.currency_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchasing.currency_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('purchasing.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('purchasing.currency_code'))->required()->maxLength(10)->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('purchasing.currency_name'))->required()->maxLength(40),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('purchasing.currency_code'))->badge(),
                TextColumn::make('name')->label(__('purchasing.currency_name'))->weight('medium'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('common.empty_state'));
    }

    public static function getPages(): array
    {
        return ['index' => ListCurrencies::route('/')];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ManagePurchases->value) ?? false;
    }
}
