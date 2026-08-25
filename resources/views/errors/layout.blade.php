{{--
    قالب مشترک صفحات خطا — با ظاهر سامانه، نه متن پیش‌فرض لاراول.
    از این فایل توسط 403/404/419/429/500/503 استفاده می‌شود.
--}}
@php $dir = config('locales.available.' . app()->getLocale() . '.dir', 'rtl'); @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}" data-theme="ocean">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __("errors.$code.title") }} — {{ \App\Support\Branding::companyName() }}</title>
    <x-favicon />
    <link rel="stylesheet" href="{{ route('theme.css') }}">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: Vazirmatn, system-ui, sans-serif; background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
            position: relative;
        }
        .error-card { text-align: center; max-width: 440px; }
        .error-card img { height: 40px; margin-bottom: 22px; }
        .error-code { font-size: 15px; font-weight: 700; color: var(--accent-text); letter-spacing: 1px; margin-bottom: 8px; }
        .error-title { font-size: 20px; font-weight: 700; margin: 0 0 12px; }
        .error-body { color: var(--muted); font-size: 14px; line-height: 1.9; margin-bottom: 26px; }
        .btn {
            display: inline-block; background: var(--accent); color: #fff; text-decoration: none;
            border-radius: 8px; padding: 10px 22px; font-size: 13.5px;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <img src="{{ \App\Support\Branding::logo('mark') }}" alt="" onerror="this.style.display='none'">
        <div class="error-code">{{ __('errors.code') }} {{ $code }}</div>
        <h1 class="error-title">{{ __("errors.$code.title") }}</h1>
        <p class="error-body">{{ __("errors.$code.body") }}</p>
        <a href="{{ url('/') }}" class="btn">{{ __('errors.back_home') }}</a>
    </div>

    <div style="position: absolute; bottom: 0; right: 0; left: 0;">
        <x-footer />
    </div>
</body>
</html>
