<?php

namespace App\Http\Middleware;

use App\Filament\Pages\LicensePage;
use App\Support\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * پس از پایان مهلت و نبودِ لایسنسِ معتبر، پنل «قفل» می‌شود: هر بارگذاری صفحه به
 * صفحهٔ لایسنس هدایت می‌شود تا کاربر کلید وارد کند. ورود همچنان ممکن است.
 *
 * فقط درخواست‌های GETِ صفحه‌ای هدایت می‌شوند؛ درخواست‌های Livewire (POST) دست
 * نمی‌خورند تا خودِ صفحهٔ لایسنس سالم کار کند.
 */
class EnsureLicensed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! auth()->check() || ! License::isLocked()) {
            return $next($request);
        }

        try {
            $licensePath = trim((string) parse_url(LicensePage::getUrl(), PHP_URL_PATH), '/');
        } catch (\Throwable) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        // اجازه: خودِ صفحهٔ لایسنس و مسیر خروج — تا حلقه نیفتد و کاربر بتواند خارج شود.
        if ($path === $licensePath || str_contains($path, 'logout')) {
            return $next($request);
        }

        return redirect(LicensePage::getUrl());
    }
}
