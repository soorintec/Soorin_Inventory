<?php

namespace App\Console\Commands;

use App\Services\AppUpdateService;
use Illuminate\Console\Command;

/**
 * بررسی روزانهٔ وجودِ نسخهٔ جدید روی گیت‌هاب و ذخیرهٔ نتیجه در کش.
 *
 * نشانِ قرمزِ منو («به‌روزرسانی») از همین کش می‌خواند تا فوری باشد. این دستور با
 * زمان‌بندِ لاراول روزی یک‌بار اجرا می‌شود (routes/console.php)؛ اگر cron هم نباشد،
 * خودِ برنامه با defer() روزی یک‌بار در پس‌زمینه کش را تازه می‌کند.
 */
class CheckUpdateCommand extends Command
{
    protected $signature = 'soorin:check-update';

    protected $description = 'بررسی وجود نسخهٔ جدید برنامه روی گیت‌هاب و کش‌کردن نتیجه';

    public function handle(AppUpdateService $service): int
    {
        $status = $service->refreshCache();

        if (! empty($status['error'])) {
            $this->warn('بررسی ناموفق: ' . $status['error']);

            return self::SUCCESS; // شکستِ شبکه نباید زمان‌بند را قرمز کند
        }

        $this->info(($status['available'] ?? false)
            ? 'نسخهٔ جدید موجود است: ' . ($status['latest'] ?? '?')
            : 'برنامه به‌روز است (' . ($status['current'] ?? '?') . ').');

        return self::SUCCESS;
    }
}
