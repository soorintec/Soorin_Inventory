<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// بررسی روزانهٔ نسخهٔ جدید روی گیت‌هاب (نشانِ قرمزِ منوی «به‌روزرسانی» از کش می‌خواند).
// اگر cron/زمان‌بند فعال نباشد، برنامه خودش با defer() روزی یک‌بار کش را تازه می‌کند.
Schedule::command('soorin:check-update')->dailyAt('03:20')->runInBackground();
