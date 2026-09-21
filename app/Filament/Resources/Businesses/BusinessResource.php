<?php

namespace App\Filament\Resources\Businesses;

use App\Enums\Permission;
use App\Filament\Resources\Businesses\Pages\CreateBusiness;
use App\Filament\Resources\Businesses\Pages\EditBusiness;
use App\Filament\Resources\Businesses\Pages\ListBusinesses;
use App\Models\Business;
use BackedEnum;
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

/**
 * کسب‌وکارها (چند-کسب‌وکاری) — رجیستریِ مرکزی. ساختِ کسب‌وکارِ تازه، دیتابیس/پیشوندِ
 * آن را می‌سازد و مهاجرت‌های tenant را اجرا می‌کند (TenantProvisioner).
 */
class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 97;

    public static function getModelLabel(): string
    {
        return __('businesses.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('businesses.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('businesses.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('backups.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('businesses.name'))->required()->maxLength(255),
            TextInput::make('code')->label(__('businesses.code'))->maxLength(30),
            Toggle::make('is_active')->label(__('common.active'))->default(true),

            // دسترسیِ کاربران به این کسب‌وکار (پیوندِ business_user).
            Select::make('users')
                ->label(__('businesses.users'))
                ->relationship('users', 'name')
                ->multiple()
                ->preload()
                ->helperText(__('businesses.users_hint')),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('businesses.name'))->searchable()->weight('medium'),
                TextColumn::make('code')->label(__('businesses.code'))->placeholder('—'),
                TextColumn::make('table_prefix')
                    ->label(__('businesses.target'))
                    ->state(fn (Business $r) => $r->database ?: ($r->table_prefix !== '' ? $r->table_prefix : __('businesses.main_target')))
                    ->badge()->color('gray'),
                TextColumn::make('users_count')->label(__('businesses.users'))->counts('users')->badge(),
                IconColumn::make('is_default')->label(__('businesses.is_default'))->boolean(),
                IconColumn::make('is_active')->label(__('common.active'))->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading(__('businesses.empty'));
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListBusinesses::route('/'),
            'create' => CreateBusiness::route('/create'),
            'edit'   => EditBusiness::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ManageBusinesses->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageBusinesses->value) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageBusinesses->value) ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false; // حذفِ کسب‌وکار (و دیتای آن) عمداً غیرفعال است — خطرِ ازدست‌رفتنِ داده.
    }
}
