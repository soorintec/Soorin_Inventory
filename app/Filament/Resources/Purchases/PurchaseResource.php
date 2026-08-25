<?php

namespace App\Filament\Resources\Purchases;

use App\Enums\Permission;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\EditPurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Purchases\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use App\Filament\Resources\Purchases\Tables\PurchasesTable;
use App\Models\Purchase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 22;

    public static function getModelLabel(): string
    {
        return __('purchasing.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchasing.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('purchasing.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPurchases::route('/'),
            'create' => CreatePurchase::route('/create'),
            'edit'   => EditPurchase::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewPurchases->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManagePurchases->value) ?? false;
    }

    /** سند دریافت‌شده دیگر قابل ویرایش نیست — قیمت‌ها و لات‌ها ثابت شده‌اند. */
    public static function canEdit(mixed $record): bool
    {
        return $record->status !== Purchase::STATUS_RECEIVED
            && (auth()->user()?->can(Permission::ManagePurchases->value) ?? false);
    }

    public static function canDelete(mixed $record): bool
    {
        return $record->status === Purchase::STATUS_DRAFT
            && (auth()->user()?->can(Permission::ManagePurchases->value) ?? false);
    }
}
