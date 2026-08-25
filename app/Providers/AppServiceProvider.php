<?php

namespace App\Providers;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Warehouse;
use App\Observers\CatalogueObserver;
use App\Observers\ProjectObserver;
use App\Observers\PurchaseObserver;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Purchase::observe(PurchaseObserver::class);
        Project::observe(ProjectObserver::class);

        // ثبت «آخرین ورود» روی رویداد ورود لاراول.
        //
        // پنل از فرم ورود پیش‌فرض فیلامنت استفاده می‌کند، پس کلاس LoginAttempt
        // هیچ‌وقت صدا زده نمی‌شد و last_login_at خالی می‌ماند؛ در نتیجه ویجت
        // «آخرین ورودها به سامانه» همیشه خالی بود. با شنیدن رویداد Login،
        // هر ورود واقعی ثبت می‌شود.
        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof \App\Models\User) {
                return;
            }

            // saveQuietly تا هوک saved کاربر (هم‌راستاسازی نقش/مجوز) بی‌جهت
            // روی هر ورود اجرا نشود.
            $event->user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ])->saveQuietly();

            try {
                \App\Models\ActivityLog::record('login', $event->user);
            } catch (\Throwable) {
                // نبود جدول سیاهه نباید مانع ورود شود
            }
        });

        // «چه کسی نام این کالا را عوض کرد؟» تا پیش از این جوابی نداشت —
        // فقط ورود و خروج انبار ثبت می‌شد.
        foreach ([Item::class, ItemVersion::class, ItemCategory::class, Warehouse::class] as $model) {
            $model::observe(CatalogueObserver::class);
        }

        // پیش‌فرض فیلامنت [۵، ۱۰، ۲۵، ۵۰] است و برای انباری با ۱۹۲ کالا کم است.
        // یک بار اینجا تنظیم می‌شود تا همه جدول‌های پنل همین گزینه‌ها را بگیرند.
        Table::configureUsing(function (Table $table): void {
            $table->paginationPageOptions([10, 25, 50, 100, 150, 200, 300, 500]);
        });
    }
}
