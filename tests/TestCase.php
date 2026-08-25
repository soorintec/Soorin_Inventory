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

        // در تست‌ها سامانه «نصب‌شده» فرض می‌شود تا میان‌افزار EnsureInstalled
        // صفحه‌ها را به ویزارد نصب نفرستد. تستِ خود ویزارد این قفل را در setUp
        // خودش برمی‌دارد.
        Installation::markInstalled();

        // کشِ فهرست ارز بین تست‌ها نشت نکند (RefreshDatabase با رول‌بکِ تراکنش،
        // رویداد deleted مدل را نمی‌زند، پس کش خودبه‌خود پاک نمی‌شود).
        Cache::forget('currencies.options');
    }
}
