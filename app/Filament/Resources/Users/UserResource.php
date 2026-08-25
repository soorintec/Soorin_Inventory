<?php

namespace App\Filament\Resources\Users;

use App\Enums\Permission;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/** فقط مدیر مجاز به ساخت/ویرایش/حذف حساب کاربری است. */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return __('users.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('users.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    /**
     * جدا کردن فیلدهای تیک دسترسی از دادهٔ فرم و تبدیلشان به یک فهرست تخت مجوز.
     *
     * صفحه‌های ساخت و ویرایش هر دو از این استفاده می‌کنند تا منطق یکسان باشد.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    public static function extractPermissionFields(array $data): array
    {
        $chosen = [];

        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, UserForm::FIELD_PREFIX)) {
                foreach ((array) $value as $permission) {
                    $chosen[] = $permission;
                }

                unset($data[$key]);
            }
        }

        return [Permission::mergeGroups([$chosen]), $data];
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit'   => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewUsers->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageUsers->value) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageUsers->value) ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        // مدیر نمی‌تواند حساب خودش را حذف کند
        return auth()->user()?->can(Permission::ManageUsers->value)
            && auth()->id() !== $record->id;
    }
}
