<?php

use App\Http\Controllers\InstallController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WarehouseReportController;
use Illuminate\Support\Facades\Route;

// ویزارد نصب وب (مثل وردپرس). تا نصب نشده، میان‌افزار EnsureInstalled هر
// آدرسی را به اینجا می‌فرستد؛ بعد از نصب، این مسیرها به /admin ریدایرکت می‌کنند.
Route::get('/install', [InstallController::class, 'show'])->name('install.show');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');

Route::get('/', fn () => redirect('/admin'));

Route::middleware('auth')->group(function () {
    Route::get('/reports/export/excel', [ReportController::class, 'excel'])->name('reports.export.excel');
    Route::get('/reports/export/pdf', [ReportController::class, 'pdf'])->name('reports.export.pdf');

    // گزارش‌های چاپی انبار — PDF درون مرورگر باز می‌شود تا مستقیم پرینت شود
    Route::get('/warehouse/print/stock', [WarehouseReportController::class, 'stockList'])->name('warehouse.print.stock');
    Route::get('/warehouse/print/flow', [WarehouseReportController::class, 'stockFlow'])->name('warehouse.print.flow');
    Route::get('/warehouse/print/reorder', [WarehouseReportController::class, 'reorder'])->name('warehouse.print.reorder');
    Route::get('/warehouse/stocktake/{stocktake}/sheet', [WarehouseReportController::class, 'stocktakeSheet'])->name('stocktake.sheet');
    Route::get('/warehouse/stocktake/{stocktake}/report', [WarehouseReportController::class, 'stocktakeReport'])->name('stocktake.report');

    // دانلود مستقیم فایل پشتیبان — لینک ساده به‌جای اکشن Livewire، تا روی
    // گوشی هم مطمئن کار کند. اعتبارسنجی نام و دسترسی همین‌جا انجام می‌شود.
    Route::get('/backups/download/{name}', function (string $name) {
        abort_unless(auth()->user()?->can(\App\Enums\Permission::ViewBackups->value), 403);

        $service = app(\App\Services\DatabaseBackupService::class);

        try {
            abort_unless($service->exists($name), 404);

            return response()->download($service->absolutePath($name));
        } catch (\RuntimeException) {
            abort(404);
        }
    })->where('name', '[A-Za-z0-9_.\-]+')->name('backups.download');

    // کلید حالت شب فیلامنت انتخاب را فقط در localStorage نگه می‌دارد؛ اینجا
    // همان انتخاب در دیتابیس هم ذخیره می‌شود تا روی دستگاه دیگر هم بماند.
    Route::post('/theme', function (Illuminate\Http\Request $request) {
        $mode = $request->validate([
            'theme' => 'required|in:light,dark,system',
        ])['theme'];

        $request->user()->saveFilamentThemeMode($mode);

        return response()->noContent();
    })->name('theme.save');
});

// تعویض زبان — هم برای کاربر واردشده (ذخیره در پروفایل) و هم مهمان (نشست).
// بیرون از گروه auth است تا صفحهٔ ورود هم بتواند زبان را عوض کند.
Route::post('/locale', function (Illuminate\Http\Request $request) {
    $available = array_keys(config('locales.available', []));

    $locale = $request->validate([
        'locale' => ['required', 'string', Illuminate\Validation\Rule::in($available)],
    ])['locale'];

    $request->session()->put('locale', $locale);

    if ($user = $request->user()) {
        $user->update(['locale' => $locale]);
    }

    return back();
})->name('locale.save');

// مسیر پیش‌فرض ورود — میان‌افزار auth لاراول برای کاربر مهمان به این نام ریدایرکت می‌کند.
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// theme.css مستقیماً از resources سرو می‌شود (نه کپی در public) — یک فایل، یک منبع حقیقت.
Route::get('/css/theme.css', function () {
    return response(file_get_contents(resource_path('css/theme.css')), 200, [
        'Content-Type'  => 'text/css',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('theme.css');
