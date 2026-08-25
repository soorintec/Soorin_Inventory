<?php

namespace App\Filament\Resources\Warehouses;

use App\Enums\Permission;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Models\Customer;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 11;

    /**
     * از منو برداشته شد و به گزینه‌ای داخل «مدیریت انبار» تبدیل شد — تعریف
     * انبار کار روزمره نیست و یک بار انجام می‌شود.
     */
    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('warehouses.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('warehouses.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('warehouses.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('warehouses.name'))->required()->maxLength(80),
            TextInput::make('code')->label(__('warehouses.code'))->required()->maxLength(20)->unique(ignoreRecord: true),

            Select::make('type')
                ->label(__('warehouses.type'))
                ->options(__('warehouses.types'))
                ->default('main')
                ->required()
                ->native(false)
                ->live(),

            Select::make('customer_id')
                ->label(__('warehouses.customer'))
                ->helperText(__('warehouses.customer_hint'))
                ->options(fn () => Customer::pluck('name', 'id'))
                ->searchable()
                ->visible(fn ($get) => $get('type') === 'consignment'),

            Toggle::make('is_active')->label(__('common.active'))->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('warehouses.name'))->searchable()->weight('medium'),
                TextColumn::make('code')->label(__('warehouses.code')),
                TextColumn::make('type')
                    ->label(__('warehouses.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("warehouses.types.$state")),
                TextColumn::make('customer.name')->label(__('warehouses.customer'))->placeholder('—'),
                IconColumn::make('is_active')->label(__('common.active'))->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('warehouses.empty'));
    }

    public static function getPages(): array
    {
        return ['index' => ListWarehouses::route('/')];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewItems->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageWarehouses->value) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageWarehouses->value) ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageWarehouses->value) ?? false;
    }
}
