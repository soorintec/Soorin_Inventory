<?php

namespace Tests;

use App\Support\Installation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // چند-کسب‌وکاری: در محیطِ تست، اتصالِ پیش‌فرض روی «tenant» تنظیم شده
        // (phpunit.xml: DB_CONNECTION=tenant)، پس هم مدل‌های مرکزی و هم مدل‌های
        // عملیاتی روی یک اتصال/یک دیتابیسِ تست می‌نشینند و RefreshDatabase و
        // تراکنش‌ها (ازجمله savepointهای سرویس‌ها) درست کار می‌کنند.

        // در تست‌ها سامانه «نصب‌شده» فرض می‌شود تا میان‌افزار EnsureInstalled
        // صفحه‌ها را به ویزارد نصب نفرستد. تستِ خود ویزارد این قفل را در setUp
        // خودش برمی‌دارد.
        Installation::markInstalled();

        // کشِ فهرست ارز بین تست‌ها نشت نکند (RefreshDatabase با رول‌بکِ تراکنش،
        // رویداد deleted مدل را نمی‌زند، پس کش خودبه‌خود پاک نمی‌شود).
        Cache::forget('currencies.options');
    }
}
