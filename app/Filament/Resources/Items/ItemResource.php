<?php

namespace App\Filament\Resources\Items;

use App\Enums\Permission;
use App\Filament\Resources\Items\Pages\CreateItem;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\Items\RelationManagers\SerialsRelationManager;
use App\Filament\Resources\Items\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\Items\Schemas\ItemForm;
use App\Filament\Resources\Items\Tables\ItemsTable;
use App\Models\Item;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('items.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('items.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('items.nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('items.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return ItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        // جدول سریال فقط برای کالایی که تیک «ثبت سریال» دارد نمایش داده می‌شود
        // (منطقش در canViewForRecord خود آن کلاس است).
        return [VersionsRelationManager::class, SerialsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListItems::route('/'),
            'create' => CreateItem::route('/create'),
            'edit'   => EditItem::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewItems->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageItems->value) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageItems->value) ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageItems->value) ?? false;
    }
}
