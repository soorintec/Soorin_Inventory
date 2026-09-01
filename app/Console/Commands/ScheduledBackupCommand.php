<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use App\Services\NetworkBackupService;
use App\Support\BackupSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * بکاپِ خودکارِ زمان‌بندی‌شده.
 *
 * زمان‌بندِ لاراول این دستور را مرتب (هر ربع‌ساعت) صدا می‌زند، و خودِ دستور
 * تصمیم می‌گیرد که آیا «اکنون» زمانِ بکاپ است یا نه — بر اساسِ تنظیماتی که مدیر
 * در صفحهٔ پشتیبان‌گیری ذخیره کرده (روزانه/هفتگی/ماهانه + ساعت). این‌طوری
 * تعریفِ زمان‌بندی در دیتابیس می‌ماند نه در کد، و مدیر می‌تواند هر وقت عوضش کند.
 *
 * پس از ساختِ بکاپ، اگر «بکاپ روی شبکه» روشن باشد، فایل روی پوشهٔ شبکه هم
 * ریخته می‌شود.
 */
class ScheduledBackupCommand extends Command
{
    protected $signature = 'soorin:scheduled-backup {--force : نادیده‌گرفتنِ زمان‌بندی و گرفتنِ بکاپ همین حالا}';

    protected $description = 'بکاپِ خودکار طبقِ زمان‌بندی (روزانه/هفتگی/ماهانه) و ریختن روی پوشهٔ شبکه';

    public function handle(DatabaseBackupService $backups, NetworkBackupService $network): int
    {
        $force = (bool) $this->option('force');

        if (! $force && ! BackupSettings::scheduleEnabled()) {
            return self::SUCCESS; // زمان‌بندی خاموش است
        }

        if (! $force && ! $this->isDue(now())) {
            return self::SUCCESS; // هنوز وقتش نرسیده یا این دوره اجرا شده
        }

        $name = $backups->create(__('backups.scheduled_reason'));
        $this->info("بکاپ ساخته شد: {$name}");

        if (BackupSettings::networkEnabled()) {
            $result = $network->push($backups->absolutePath($name), $name);

            $result['ok']
                ? $this->info($result['message'])
                : $this->warn('شبکه: ' . $result['message']);
        }

        BackupSettings::markRan(now());

        return self::SUCCESS;
    }

    /**
     * آیا هم‌اکنون باید بکاپ گرفته شود؟
     *
     * منطق: ساعتِ مقررِ «امروز» را می‌سازیم؛ اگر از آن گذشته‌ایم و در این دوره
     * (روز/هفته/ماه) هنوز بکاپی گرفته نشده، بله. روزِ هفته/ماه هم بررسی می‌شود.
     */
    public function isDue(Carbon $now): bool
    {
        [$hour, $minute] = array_map('intval', explode(':', BackupSettings::time()));
        $target = $now->copy()->setTime($hour, $minute, 0);

        // هنوز به ساعتِ مقررِ امروز نرسیده‌ایم.
        if ($now->lt($target)) {
            return false;
        }

        // گیتِ فراوانی: هفتگی فقط در روزِ تعیین‌شده، ماهانه فقط در روزِ تعیین‌شده.
        $frequency = BackupSettings::frequency();

        if ($frequency === 'weekly' && $now->dayOfWeek !== BackupSettings::weekday()) {
            return false;
        }

        if ($frequency === 'monthly' && $now->day !== BackupSettings::monthday()) {
            return false;
        }

        // جلوگیری از اجرای دوباره در همین دوره: اگر آخرین اجرا بعد از ساعتِ
        // مقررِ امروز بوده، یعنی همین امروز گرفته شده.
        $last = BackupSettings::lastRun();

        if ($last !== null && $last->gte($target)) {
            return false;
        }

        return true;
    }
}
