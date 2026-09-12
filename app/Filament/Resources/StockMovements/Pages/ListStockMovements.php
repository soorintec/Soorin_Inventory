<?php

namespace App\Filament\Resources\StockMovements\Pages;

use App\Enums\Permission;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Services\StockMovementService;
use App\Support\Jalali;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

/**
 * تاریخچه تراکنش‌های انبار — فقط‌خواندنی.
 *
 * دکمه «ایجاد» عمداً حذف شد: این Resource فرم ندارد، پس دکمه پنجره‌ای خالی
 * باز می‌کرد و ثبتش با خطای «item_version_id مقدار پیش‌فرض ندارد» شکست
 * می‌خورد. مهم‌تر از آن، ساخت دستی سند تراکنش قاعده ۳ و ۴ پروژه را می‌شکند:
 * هر تراکنش باید از StockMovementService بگذرد تا لات FIFO و موجودی خلاصه
 * با هم هم‌خوان بمانند. ثبت ورود و خروج از صفحه «مدیریت انبار» انجام می‌شود.
 *
 * تنها استثنا، دکمهٔ «پاک‌سازیِ کلِ لاگ» است که فقط با مجوزِ اختصاصیِ
 * PurgeStockMovements (پیش‌فرض فقط ادمین) دیده می‌شود.
 */
class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    public function getSubheading(): ?string
    {
        return __('stock.movements_intro');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->purgeAction(),
        ];
    }

    /**
     * پاک‌سازیِ کاملِ لاگِ تراکنش‌ها — استثنای مجوزدار بر قاعدهٔ «سند انبار حذف
     * نمی‌شود». موجودی و قیمتِ جاری دست نمی‌خورد؛ فقط سابقهٔ حرکت‌ها/کاردکس صفر
     * می‌شود. تیکِ تأیید عمداً اجباری است چون برگشت‌ناپذیر است.
     */
    private function purgeAction(): Action
    {
        return Action::make('purgeMovements')
            ->label(__('stock.purge_label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn () => auth()->user()?->can(Permission::PurgeStockMovements->value) ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('stock.purge_heading'))
            ->modalDescription(__('stock.purge_warning'))
            ->modalSubmitActionLabel(__('stock.purge_confirm_button'))
            ->action(function (StockMovementService $service): void {
                $count = $service->purgeLog();

                Notification::make()
                    ->title(__('stock.purge_done', ['count' => Jalali::digits((string) $count)]))
                    ->success()
                    ->send();
            });
    }
}
