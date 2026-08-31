<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        @if ($record->imageUrl())
            <img src="{{ $record->imageUrl() }}" alt="" class="h-11 w-11 rounded-lg object-cover ring-1 ring-gray-200 dark:ring-gray-700">
        @endif
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('items.total_stock') }}:</span>
        <x-filament::badge :color="$balance > 0 ? 'success' : ($balance < 0 ? 'danger' : 'gray')" size="lg">
            {{ \App\Support\Jalali::quantity($balance) }} {{ $record->unit }}
        </x-filament::badge>
        <span class="text-xs text-gray-400">{{ $record->code }}</span>
    </div>

    <x-filament::section>
        @if (empty($rows))
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('stock.kardex_empty') }}</p>
        @else
            <div class="calc-parts-scroll overflow-x-auto">
                <table class="calc-parts w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-start font-medium">#</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('common.date') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('stock.warehouse') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('items.version_label') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('stock.reason') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('stock.directions.in') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('stock.directions.out') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('stock.balance') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('stock.user') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ __('common.notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                            @php $m = $row['movement']; $isIn = $m->direction === \App\Models\StockMovement::DIRECTION_IN; @endphp
                            {{-- ردیفِ ورود سبز، ردیفِ خروج قرمز (رنگ درون‌خطی تا مستقل از کلاس‌های تیلویند قطعی باشد) --}}
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800"
                                style="background: {{ $isIn ? 'rgba(16,185,129,0.09)' : 'rgba(239,68,68,0.09)' }}">
                                <td class="px-4 py-3 text-gray-400">{{ \App\Support\Jalali::digits((string) ($i + 1)) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ \App\Support\Jalali::formatDateTime($m->created_at) }}</td>
                                <td class="px-4 py-3">{{ $m->warehouse?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $m->itemVersion?->version_code ?? '—' }}</td>
                                <td class="px-4 py-3">{{ __('stock.reasons.' . $m->reason) }}</td>
                                <td class="px-4 py-3 font-semibold text-green-600 dark:text-green-400">
                                    {{ $isIn ? \App\Support\Jalali::quantity($m->quantity) : '' }}
                                </td>
                                <td class="px-4 py-3 font-semibold text-red-600 dark:text-red-400">
                                    {{ $isIn ? '' : \App\Support\Jalali::quantity($m->quantity) }}
                                </td>
                                <td class="px-4 py-3 font-bold">{{ \App\Support\Jalali::quantity($row['balance']) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $m->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $m->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
