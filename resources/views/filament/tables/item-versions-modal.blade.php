{{--
    فهرست ورژن‌های یک کالا در پنجرهٔ «ورژن‌ها» صفحهٔ موجودی انبار.

    فاصله ستون‌ها با style درون‌خطی است، نه کلاس Tailwind: کلاس‌هایی مثل px-5
    در CSS ازپیش‌ساختهٔ فیلامنت نیستند و اعمال نمی‌شدند.
--}}
@php
    /** @var \App\Models\Item $item */
    $versions = $item->versions()->orderBy('version_code')->get();

    $cell = 'padding:9px 22px; white-space:nowrap; text-align:start;';
    $head = 'padding:9px 22px; text-align:start; font-weight:600; white-space:nowrap;';
@endphp

@if ($versions->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('items.empty_versions_inline') }}</p>
@else
    <div style="overflow-x:auto;">
        <table class="text-sm" style="border-collapse:collapse; min-width:100%;">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th style="{{ $head }}">{{ __('items.version_code') }}</th>
                    <th style="{{ $head }}">{{ __('items.location') }}</th>
                    <th style="{{ $head }}">{{ __('items.current_stock') }}</th>
                    <th style="{{ $head }}">{{ __('items.fx_price') }}</th>
                    <th style="padding:9px 22px; text-align:start; font-weight:600;">{{ __('items.notes') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($versions as $version)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <td style="{{ $cell }} font-weight:500;">{{ $version->version_code }}</td>

                        <td style="{{ $cell }}">
                            @if ($version->location)
                                <span dir="ltr" class="font-mono text-xs">{{ $version->location }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        <td style="{{ $cell }} font-weight:600;">
                            {{ \App\Support\Jalali::quantity($version->totalQuantity()) }}
                            <span class="text-xs font-normal text-gray-500">{{ $item->unit }}</span>
                        </td>

                        <td style="{{ $cell }}">
                            @if ($label = $version->fxPriceLabel())
                                <span class="text-primary-600 dark:text-primary-400">{{ $label }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        <td style="padding:9px 22px; text-align:start;" class="text-gray-600 dark:text-gray-400">
                            {{ $version->notes ?: '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
