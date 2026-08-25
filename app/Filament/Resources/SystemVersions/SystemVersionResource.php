<?php

namespace App\Filament\Resources\SystemVersions;

use App\Enums\Permission;
use App\Filament\Resources\SystemModels\SystemModelResource;
use App\Filament\Resources\SystemVersions\Pages\ViewSystemVersion;
use App\Filament\Resources\SystemVersions\RelationManagers\BomRelationManager;
use App\Models\SystemVersion;
use Filament\Resources\Resource;

/**
 * نسخه یک مدل سامانه به‌همراه لیست قطعاتش.
 *
 * از منو ثبت نمی‌شود؛ از صفحه «مدل‌های سامانه» با کلیک روی نسخه باز می‌شود.
 * دلیل وجود این Resource فقط داشتن یک صفحه کامل برای BOM است — لیست قطعات
 * در پنجره کوچک جا نمی‌شود و باید موجودی و کسری را هم کنارش نشان بدهد.
 */
class SystemVersionResource extends Resource
{
    protected static ?string $model = SystemVersion::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('systems.version_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('systems.version_plural');
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): ?string
    {
        return $record?->displayName();
    }

    /**
     * این Resource صفحه فهرست ندارد (از دل مدل سامانه باز می‌شود)، ولی فیلامنت
     * برای ساختن نان‌ریزه‌ها به یک آدرس ریشه نیاز دارد. فهرست مدل‌ها همان
     * جایی است که کاربر از آن آمده.
     */
    public static function getIndexUrl(
        array $parameters = [],
        bool $isAbsolute = true,
        ?string $panel = null,
        ?\Illuminate\Database\Eloquent\Model $tenant = null,
        bool $shouldGuessMissingParameters = false,
    ): string {
        return SystemModelResource::getUrl('index', panel: $panel, isAbsolute: $isAbsolute);
    }

    public static function getRelations(): array
    {
        return [BomRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewSystemVersion::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewProjects->value) ?? false;
    }

    public static function canView(mixed $record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageSystemModels->value) ?? false;
    }
}
