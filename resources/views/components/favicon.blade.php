{{-- فاوآیکون چندسایزی — اگر مدیر فاوآیکونِ سفارشی گذاشته باشد همان مقدم است --}}
@if (\App\Support\Branding::hasCustomLogo('favicon'))
    <link rel="icon" href="{{ \App\Support\Branding::logo('favicon') }}">
    <link rel="apple-touch-icon" href="{{ \App\Support\Branding::logo('favicon') }}">
@else
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon-192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicon-180.png') }}">
@endif
