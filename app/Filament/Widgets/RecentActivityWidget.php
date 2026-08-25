<?php

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * باکس «آخرین تغییرات انبار» بالای صفحه موجودی.
 *
 * جواب سؤالی است که همیشه پرسیده می‌شود: «این کالا کی و توسط چه کسی کم شد؟»
 * زمان‌ها به وقت تهران و تاریخ شمسی نمایش داده می‌شوند.
 */
class RecentActivityWidget extends Widget
{
    protected string $view = 'filament.widgets.recent-activity';

    protected int|string|array $columnSpan = 'full';

    /** ویجت‌ها پیش‌فرض تنبل‌اند و در تست رندر نمی‌شوند. */
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->can(\App\Enums\Permission::ViewStock->value) ?? false;
    }

    /** چند سطر آخر — بیشتر از این، باکس صفحه را می‌بلعد. */
    private const LIMIT = 8;

    public function getActivities(): Collection
    {
        return ActivityLog::query()
            ->with('user')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();
    }
}
