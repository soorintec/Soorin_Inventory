<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * کسب‌وکارِ فعال (tenant) را برای هر درخواست تعیین و روی اتصالِ tenant سوار می‌کند.
 *
 * ترتیب: انتخابِ ذخیره‌شده در نشست (اگر کاربر به آن دسترسی دارد) → اولین
 * کسب‌وکارِ در دسترسِ کاربر → کسب‌وکارِ پیش‌فرض. برای تنها-کسب‌وکارِ پیش‌فرض این
 * کار عملاً no-op است (اتصالِ tenant همان دیتابیسِ فعلی می‌ماند).
 */
class SetActiveBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        // پیش از نصب/مهاجرت، جدولِ کسب‌وکارها هنوز نیست — بی‌سروصدا رد شو.
        try {
            if (! Schema::hasTable('businesses')) {
                return $next($request);
            }
        } catch (\Throwable) {
            return $next($request);
        }

        $business = $this->resolve($request);

        if ($business !== null) {
            Tenancy::use($business);
        }

        return $next($request);
    }

    private function resolve(Request $request): ?Business
    {
        $user = $request->user();

        // کسب‌وکارهای در دسترس (برای مهمان: همهٔ فعال‌ها؛ در عمل صفحهٔ ورود tenant نمی‌خواهد).
        $accessible = $user
            ? $user->accessibleBusinesses()
            : Business::query()->where('is_active', true)->orderBy('id')->get();

        if ($accessible->isEmpty()) {
            return Business::default();
        }

        $selectedId = $request->session()->get('active_business_id');

        if ($selectedId && ($match = $accessible->firstWhere('id', (int) $selectedId))) {
            return $match;
        }

        // پیش‌فرض اگر در دسترس بود، وگرنه اولین کسب‌وکارِ مجاز.
        $default = $accessible->firstWhere('is_default', true);

        return $default ?? $accessible->first();
    }
}
