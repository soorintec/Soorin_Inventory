<?php

namespace App\Http\Middleware;

use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * تا وقتی سامانه نصب نشده، هر درخواستی به ویزارد نصب (/install) هدایت می‌شود.
 * خودِ مسیر نصب مستثناست تا حلقه نیفتد. بعد از نصب، این میان‌افزار بی‌اثر است.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Installation::isInstalled()) {
            return $next($request);
        }

        if ($request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        return redirect('/install');
    }
}
