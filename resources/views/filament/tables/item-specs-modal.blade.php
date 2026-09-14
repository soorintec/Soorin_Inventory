{{--
    جدولِ مشخصات فنیِ یک کالا در پنجرهٔ «مشخصات فنی» صفحهٔ موجودی انبار.
    فقط‌خواندنی؛ ویرایش از «مدیریت انبار ← ویرایش کالا» انجام می‌شود.
--}}
@php
    /** @var \App\Models\Item $item */
    $rows = collect($item->specs ?? [])
        ->filter(fn ($r) => filled($r['label'] ?? null) || filled($r['value'] ?? null));

    $cell = 'padding:9px 22px; text-align:start; vertical-align:top;';
    $head = 'padding:9px 22px; text-align:start; font-weight:600; white-space:nowrap;';
@endphp

@if ($rows->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('items.specs_empty') }}</p>
@else
    <div style="overflow-x:auto;">
        <table class="text-sm" style="border-collapse:collapse; min-width:100%;">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th style="{{ $head }}">{{ __('items.spec_row_label') }}</th>
                    <th style="{{ $head }}">{{ __('items.spec_row_value') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <td style="{{ $cell }} font-weight:500;">{{ $row['label'] ?? '—' }}</td>
                        <td style="{{ $cell }}" class="text-gray-600 dark:text-gray-400">{{ $row['value'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
