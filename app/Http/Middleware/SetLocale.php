<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * زبان فعال هر درخواست را تعیین می‌کند.
 *
 * ترتیب: زبانِ کاربرِ واردشده → زبانِ ذخیره‌شده در نشست (برای مهمان/صفحه ورود)
 * → پیش‌فرضِ config. فقط زبان‌های تعریف‌شده در config/locales.php پذیرفته می‌شوند.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('locales.available', []));
        $default = config('locales.default', config('app.locale'));

        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $default;

        if (! in_array($locale, $available, true)) {
            $locale = $default;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
