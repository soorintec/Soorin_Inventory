{{-- قالب مشترک گزارش‌های چاپی انبار — جهت بر اساس زبان فعال، فونت وزیرمتن. --}}
@php $dir = config('locales.available.' . app()->getLocale() . '.dir', 'rtl'); @endphp
<!DOCTYPE html>
<html dir="{{ $dir }}" lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: vazirmatn; font-size: 10pt; color: #0b2b3f; direction: {{ $dir }}; }
    table { width: 100%; border-collapse: collapse; }
    .company-name { font-size: 13pt; font-weight: bold; color: #0f2d4d; }
    .company-web { font-size: 8.5pt; color: #5f7d8c; }
    .report-title { font-size: 14pt; font-weight: bold; color: #0f766e; text-align: left; }
    .meta { font-size: 9pt; color: #5f7d8c; text-align: left; }
    .divider { border-top: 2px solid #0f2d4d; margin: 6px 0 10px; }
    .section-title { font-size: 11.5pt; font-weight: bold; color: #0f2d4d; margin: 14px 0 6px; }
    /* محتوای همه سلول‌ها وسط‌چین است (خواسته مالک پروژه): تعداد ۶ یا محل A1
       وسط سلول بنشیند نه گوشه. اعداد و متن لاتین با direction:ltr درست
       نمایش داده می‌شوند ولی باز هم وسط قرار می‌گیرند. */
    .data-table th { background: #0f2d4d; color: #fff; padding: 5px 7px; font-size: 8.5pt; text-align: center; }
    .data-table td { padding: 4px 7px; font-size: 8.5pt; border-bottom: 1px solid #dde8ec; text-align: center; }
    .data-table tr:nth-child(even) td { background: #f5f9fa; }
    .num { text-align: center; direction: ltr; }
    .ltr { direction: ltr; text-align: center; }
    .muted { color: #5f7d8c; }
    .danger { color: #b91c1c; font-weight: bold; }
    .success { color: #047857; font-weight: bold; }
    /* خانه خالی برای نوشتن دستی در فهرست شمارش */
    .write-in { border: 1px solid #94a3b8; height: 16px; min-width: 60px; }
    .summary-box { border: 1px solid #dde8ec; padding: 6px 10px; font-size: 9pt; }
    .footer-note { margin-top: 18px; font-size: 8pt; color: #5f7d8c; text-align: center; }
</style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 55%;">
                <table><tr>
                    @if (! empty($logo ?? null))
                        <td style="width: 52px; vertical-align: middle;">
                            <img src="{{ $logo }}" alt="" style="height: 42px; width: auto;">
                        </td>
                    @endif
                    <td style="vertical-align: middle;">
                        <div class="company-name">{{ $company['name'] }}</div>
                        <div class="company-web">{{ $company['website_label'] }} — {{ $app['title'] }}</div>
                    </td>
                </tr></table>
            </td>
            <td style="width: 45%;">
                <div class="report-title">{{ $title }}</div>
                <div class="meta">@yield('meta')</div>
                <div class="meta">{{ __('reports.printed_at') }}: {{ $printedAt }}</div>
            </td>
        </tr>
    </table>
    <div class="divider"></div>

    @yield('content')

    <div class="footer-note">
        {{ $company['name'] }}@if (! empty($company['website_label'])) — {{ $company['website_label'] }}@endif
        @if (! empty($company['phone'])) · {{ __('common.phone') }}: {{ $company['phone'] }}@endif
        @if (! empty($company['address']))<br>{{ $company['address'] }}@endif
    </div>
</body>
</html>
