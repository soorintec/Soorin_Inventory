<?php

namespace App\Filament\Resources\CustomerSystems;

use App\Enums\Permission;
use App\Filament\Resources\CustomerSystems\Pages\CreateCustomerSystem;
use App\Filament\Resources\CustomerSystems\Pages\EditCustomerSystem;
use App\Filament\Resources\CustomerSystems\Pages\ListCustomerSystems;
use App\Filament\Resources\CustomerSystems\RelationManagers\PartsRelationManager;
use App\Filament\Resources\CustomerSystems\Schemas\CustomerSystemForm;
use App\Filament\Resources\CustomerSystems\Tables\CustomerSystemsTable;
use App\Models\CustomerSystem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerSystemResource extends Resource
{
    protected static ?string $model = CustomerSystem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?int $navigationSort = 32;

    public static function getModelLabel(): string
    {
        return __('systems.customer_system_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('systems.customer_system_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('systems.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerSystemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerSystemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [PartsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCustomerSystems::route('/'),
            'create' => CreateCustomerSystem::route('/create'),
            'edit'   => EditCustomerSystem::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewProjects->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageProjects->value) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageProjects->value) ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageProjects->value) ?? false;
    }
}
