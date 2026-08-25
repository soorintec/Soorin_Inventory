<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('calculator.intro') }}</p>

    <form wire:submit.prevent="calculate">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" icon="heroicon-o-calculator">
                {{ __('calculator.calculate') }}
            </x-filament::button>
        </div>
    </form>

    @php $r = $this->result; @endphp

    @if (! empty($r))
        @if (empty($r['has_selection']))
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('calculator.empty') }}</p>
            </x-filament::section>
        @else
            {{-- برآورد هزینه به تفکیک ارز --}}
            <x-filament::section>
                <x-slot name="heading">{{ __('calculator.totals_title') }}</x-slot>

                @if (empty($r['totals']))
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('calculator.no_price') }}</p>
                @else
                    <div class="flex flex-wrap gap-4">
                        @foreach ($r['totals'] as $t)
                            <div class="rounded-lg border border-gray-200 px-5 py-3 dark:border-gray-700">
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('calculator.total', ['currency' => $t['label']]) }}</div>
                                <div class="text-xl font-bold">{{ \App\Support\Jalali::quantity($t['value']) }} {{ $t['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ __('calculator.price_note') }}</p>
            </x-filament::section>

            {{-- فهرست قطعات --}}
            <x-filament::section>
                <x-slot name="heading">{{ __('calculator.result_title') }}</x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-6 py-5 text-start font-medium">{{ __('calculator.col_row') }}</th>
                                <th class="px-6 py-5 text-start font-medium">{{ __('calculator.col_item') }}</th>
                                <th class="px-6 py-5 text-start font-medium">{{ __('calculator.col_required') }}</th>
                                <th class="px-6 py-5 text-start font-medium">{{ __('calculator.col_unit_price') }}</th>
                                <th class="px-6 py-5 text-start font-medium">{{ __('calculator.col_line_total') }}</th>
                                <th class="px-6 py-5 text-start font-medium">{{ __('calculator.col_stock') }}</th>
                                <th class="px-6 py-5 text-start font-medium">{{ __('calculator.col_status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($r['rows'] as $i => $row)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                    <td class="px-6 py-6">{{ \App\Support\Jalali::digits((string) ($i + 1)) }}</td>
                                    <td class="px-6 py-6 font-medium">
                                        {{ $row['item'] }}
                                        <span class="text-xs text-gray-400" dir="ltr">{{ $row['code'] }}</span>
                                    </td>
                                    <td class="px-6 py-6 font-semibold whitespace-nowrap">
                                        {{ \App\Support\Jalali::quantity($row['required']) }}
                                        <span class="text-xs font-normal text-gray-500">{{ $row['unit'] }}</span>
                                    </td>
                                    <td class="px-6 py-6 whitespace-nowrap">
                                        @if ($row['unit_price'] !== null)
                                            {{ \App\Support\Jalali::quantity($row['unit_price']) }} {{ $row['currency_label'] }}
                                        @else
                                            <span class="text-gray-400">{{ __('calculator.no_price') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-6 font-semibold whitespace-nowrap">
                                        @if ($row['line_total'] !== null)
                                            {{ \App\Support\Jalali::quantity($row['line_total']) }} {{ $row['currency_label'] }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-6 whitespace-nowrap">{{ \App\Support\Jalali::quantity($row['stock']) }}</td>
                                    <td class="px-6 py-6 whitespace-nowrap">
                                        @if ($row['shortage'] > 0)
                                            <x-filament::badge color="danger">
                                                {{ __('calculator.shortage', ['count' => \App\Support\Jalali::quantity($row['shortage'])]) }}
                                            </x-filament::badge>
                                        @else
                                            <x-filament::badge color="success">{{ __('calculator.in_stock') }}</x-filament::badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
