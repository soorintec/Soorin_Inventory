<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * تم انتخابی هر کاربر (ocean یا night) را در دسترس نماها قرار می‌دهد.
 * مقدار روی <html data-theme="..."> می‌نشیند و متغیرهای CSS را عوض می‌کند.
 */
class ApplyUserTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        $theme = $request->user()?->theme ?? config('branding.default_theme');

        if (! array_key_exists($theme, config('branding.themes'))) {
            $theme = config('branding.default_theme');
        }

        View::share('activeTheme', $theme);

        return $next($request);
    }
}
