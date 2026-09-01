<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// بررسی روزانهٔ نسخهٔ جدید روی گیت‌هاب (نشانِ قرمزِ منوی «به‌روزرسانی» از کش می‌خواند).
// اگر cron/زمان‌بند فعال نباشد، برنامه خودش با defer() روزی یک‌بار کش را تازه می‌کند.
Schedule::command('soorin:check-update')->dailyAt('03:20')->runInBackground();

// ضربانِ زمان‌بند + بررسیِ سررسیدِ بکاپ — هر دقیقه، اما «درون‌پردازه‌ای»
// (بدونِ ساختنِ پردازهٔ PHP جدید) تا بارِ اضافه‌ای روی سرور نیفتد. کلِ کارِ این
// بلاک وقتی بکاپی لازم نیست، فقط چند خواندن/نوشتنِ کوچک روی جدولِ settings است.
//
//  ۱) ضربان: هر دقیقه یک مُهرِ زمانی می‌نویسد تا صفحهٔ پشتیبان‌گیری بفهمد که
//     زمان‌بندِ سیستم‌عامل (systemd/cron) واقعاً می‌دود یا نه. کهنه‌ماندنِ این مُهر،
//     رایج‌ترین علتِ «بکاپِ خودکار نگرفت» را آشکار می‌کند.
//  ۲) بکاپِ خودکار: فقط اگر زمان‌بندی روشن باشد، دستور را در همین پردازه صدا
//     می‌زند (Artisan::call، نه پردازهٔ جدید)؛ خودِ دستور تصمیم می‌گیرد که سرِ
//     ساعتِ تنظیم‌شده رسیده یا نه و در صورتِ نیاز بکاپ می‌گیرد و روی شبکه می‌ریزد.
Schedule::call(function () {
    Setting::set('backup.scheduler_heartbeat', now()->toIso8601String(), 'backup', 'string');

    if (\App\Support\BackupSettings::scheduleEnabled()) {
        try {
            Artisan::call('soorin:scheduled-backup');
        } catch (\Throwable $e) {
            report($e); // شکستِ بکاپ نباید ضربان یا بقیهٔ زمان‌بند را متوقف کند
        }
    }
})->everyMinute()->name('soorin-scheduler-tick')->withoutOverlapping();
