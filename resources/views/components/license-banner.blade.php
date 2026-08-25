{{-- بنر دورهٔ آزمایشی — فقط وقتی لایسنس نیست و هنوز در مهلت هستیم --}}
@php $s = \App\Support\License::status(); @endphp

@if (! ($s['licensed'] ?? false) && ($s['in_grace'] ?? false))
    <div style="background:#b45309; color:#fff; text-align:center; padding:7px 14px; font-size:13px;">
        {{ __('license.grace_left', ['days' => \App\Support\Jalali::digits((string) ($s['grace_days_left'] ?? 0))]) }}
        <a href="{{ \App\Filament\Pages\LicensePage::getUrl() }}" style="color:#fff; text-decoration:underline; font-weight:700; margin-inline-start:8px;">
            {{ __('license.label') }}
        </a>
    </div>
@endif
