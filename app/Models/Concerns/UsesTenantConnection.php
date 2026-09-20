<?php

namespace App\Models\Concerns;

/**
 * مدل‌های عملیاتیِ کسب‌وکار (کالا، انبار، مشتری، پروژه…) روی اتصالِ «tenant»
 * می‌نشینند تا با سوییچِ کسب‌وکارِ فعال، دیتای هر کسب‌وکار جدا بماند.
 *
 * جدول‌های مرکزی (کاربران، مجوزها، لایسنس، رجیستریِ کسب‌وکارها) روی اتصالِ
 * پیش‌فرض (central) می‌مانند و این trait را نمی‌گیرند.
 */
trait UsesTenantConnection
{
    public function getConnectionName(): ?string
    {
        return 'tenant';
    }
}
