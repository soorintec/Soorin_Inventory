<?php

namespace App\Providers\Filament;

use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $ocean = config('branding.themes.ocean');

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // نام و لوگو از App\Support\Branding خوانده می‌شوند تا شخصی‌سازیِ مدیر
            // (صفحهٔ «شخصی‌سازی») همین‌جا هم اعمال شود، نه فقط پیش‌فرضِ config.
            ->brandName(fn () => \App\Support\Branding::appTitle())
            // لوگو به‌صورت Htmlable برگردانده می‌شود تا نام سامانه کنارش بنشیند؛
            // فیلامنت رشته ساده را فقط به‌عنوان src یک <img> می‌گیرد.
            ->brandLogo(fn () => view('components.brand', [
                'logo' => \App\Support\Branding::logo('light'),
                'name' => \App\Support\Branding::appTitle(),
            ]))
            ->darkModeBrandLogo(fn () => view('components.brand', [
                'logo' => \App\Support\Branding::logo('dark'),
                'name' => \App\Support\Branding::appTitle(),
            ]))
            // پیش‌فرض فیلامنت ۱.۵rem است و لوگوی سورین در آن اندازه خوانا نیست
            // (نسبت تصویر ۸۸۹×۶۰۷ و خط «TABARESTAN» زیر آن). ۳rem انتخاب شد و
            // ارتفاع نوار بالا در theme.css به ۴.۷۵rem رسید تا فاصله بالا و
            // پایین قرینه بماند.
            ->brandLogoHeight('3rem')
            ->favicon(fn () => \App\Support\Branding::logo('favicon'))
            ->colors([
                'primary' => $ocean['accent'],
                'gray'    => '#5f7d8c',
            ])
            ->font('Vazirmatn', url: asset('css/fonts.css'), provider: LocalFontProvider::class)
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // پیش از اسکریپت حالت شب فیلامنت اجرا می‌شود تا تم ذخیره‌شده کاربر
            // در localStorage بنشیند و فیلامنت همان را اعمال کند.
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn () => view('components.theme-sync', [
                    'theme' => auth()->user()?->filamentThemeMode() ?? 'light',
                ]),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                // ?v=نسخه تا بعد از هر به‌روزرسانی، مرورگر CSSِ تازه را بگیرد و
                // نسخهٔ کش‌شدهٔ قدیمی را نشان ندهد.
                fn () => '<link rel="stylesheet" href="' . route('theme.css') . '?v=' . \App\Support\AppVersion::current() . '">',
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn () => view('components.footer'),
            )
            // سوییچ زبان — در نوار بالا و روی صفحهٔ ورود.
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn () => view('components.locale-switcher'),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => view('components.locale-switcher'),
            )
            // بنر دورهٔ آزمایشی لایسنس (بالای صفحه).
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => view('components.license-banner'),
            )
            // نقطهٔ قرمزِ «نسخهٔ جدید» کنار تیترِ گروهِ منو.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('components.update-nav-indicator'),
            )
            ->middleware([
                // تا نصب نشده، پنل هم به ویزارد نصب می‌رود (نه صفحه ورود).
                \App\Http\Middleware\EnsureInstalled::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                // پس از StartSession تا زبانِ کاربر/نشست خوانده شود؛ جهت RTL/LTR و
                // ترجمهٔ خودِ فیلامنت هم به همین locale وابسته است.
                \App\Http\Middleware\SetLocale::class,
                ShareErrorsFromSession::class,
                // پس از خواندن نشست و زبان: قفلِ لایسنس (پس از پایان مهلت).
                \App\Http\Middleware\EnsureLicensed::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
