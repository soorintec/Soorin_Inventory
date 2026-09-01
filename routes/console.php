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

// بکاپِ خودکارِ زمان‌بندی‌شده. هر دقیقه بررسی می‌شود تا دقیقاً سرِ ساعتِ تنظیم‌شده
// اجرا شود (نه با تأخیرِ ربع‌ساعته)؛ خودِ دستور تصمیم می‌گیرد که طبقِ تنظیماتِ مدیر
// (روزانه/هفتگی/ماهانه + ساعت) اکنون وقتِ بکاپ است یا نه، و در صورتِ روشن‌بودنِ
// «بکاپ روی شبکه» فایل را آنجا هم می‌ریزد. عمداً runInBackground ندارد تا اگر خطایی
// داد، در لاگِ زمان‌بند دیده شود نه اینکه بی‌صدا گم شود.
Schedule::command('soorin:scheduled-backup')->everyMinute()->withoutOverlapping();

// ضربانِ زمان‌بند: هر دقیقه یک مُهرِ زمانی می‌نویسد تا صفحهٔ پشتیبان‌گیری بتواند
// نشان دهد که آیا زمان‌بندِ سیستم‌عامل (systemd/cron) واقعاً در حال اجراست یا نه.
// اگر این مُهر کهنه بماند، یعنی schedule:run اصلاً اجرا نمی‌شود — و علتِ رایجِ
// «بکاپِ خودکار نگرفت» همین است.
Schedule::call(function () {
    Setting::set('backup.scheduler_heartbeat', now()->toIso8601String(), 'backup', 'string');
})->everyMinute()->name('soorin-scheduler-heartbeat');
