<x-filament-panels::page>
    <form wire:submit.prevent="applyPreset">
        {{ $this->form }}
    </form>

    @php $r = $this->report; $p = $r['purchases'] ?? []; @endphp

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.purchase_count') }}</div>
            <div class="text-xl font-bold">{{ \App\Support\Jalali::quantity($p['count'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.purchase_goods') }}</div>
            <div class="text-xl font-bold">{{ \App\Support\Jalali::money($p['goods'] ?? 0) }}</div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500">{{ __('reports.purchase_total') }}</div>
            <div class="text-xl font-bold">{{ \App\Support\Jalali::money($p['total_cost'] ?? 0) }}</div>
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('reports.by_user')">
        @if (empty($r['by_user']) || count($r['by_user']) === 0)
            <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
        @else
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="text-gray-500 border-b">
                    <th class="p-2 text-right">{{ __('reports.col_user') }}</th>
                    <th class="p-2 text-right">{{ __('reports.col_in_count') }}</th>
                    <th class="p-2 text-right">{{ __('reports.col_out_count') }}</th>
                    <th class="p-2 text-right">{{ __('reports.col_in_qty') }}</th>
                    <th class="p-2 text-right">{{ __('reports.col_out_qty') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($r['by_user'] as $row)
                        <tr class="border-b">
                            <td class="p-2">{{ $row['user'] }}</td>
                            <td class="p-2">{{ \App\Support\Jalali::quantity($row['in_count']) }}</td>
                            <td class="p-2">{{ \App\Support\Jalali::quantity($row['out_count']) }}</td>
                            <td class="p-2">{{ \App\Support\Jalali::quantity($row['in_qty']) }}</td>
                            <td class="p-2">{{ \App\Support\Jalali::quantity($row['out_qty']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('reports.system_costs')">
        @if (empty($r['system_costs']) || count($r['system_costs']) === 0)
            <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
        @else
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="text-gray-500 border-b">
                    <th class="p-2 text-right">{{ __('reports.col_code') }}</th>
                    <th class="p-2 text-right">{{ __('reports.col_system') }}</th>
                    <th class="p-2 text-right">{{ __('reports.col_customer') }}</th>
                    <th class="p-2 text-right">{{ __('reports.col_cost') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($r['system_costs'] as $row)
                        <tr class="border-b">
                            <td class="p-2" style="font-family:monospace">{{ $row['code'] }}</td>
                            <td class="p-2">{{ $row['name'] }}</td>
                            <td class="p-2">{{ $row['customer'] }}</td>
                            <td class="p-2">{{ \App\Support\Jalali::money($row['cost']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('reports.stock_levels')">
        @if (empty($r['stock_levels']) || count($r['stock_levels']) === 0)
            <p class="text-sm text-gray-500">{{ __('reports.empty') }}</p>
        @else
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="text-gray-500 border-b">
                    <th class="p-2 text-right">{{ __('reports.col_item') }}</th>
                    <th class="p-2 text-right">{{ __('reports.col_version') }}</th>
                    <th class="p-2 text-right">{{ __('reports.col_qty') }}</th>
                    <th class="p-2 text-right">{{ __('reports.col_value') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($r['stock_levels'] as $row)
                        <tr class="border-b">
                            <td class="p-2">{{ $row['item'] }}</td>
                            <td class="p-2">{{ $row['version'] }}</td>
                            <td class="p-2">{{ \App\Support\Jalali::quantity($row['qty']) }}</td>
                            <td class="p-2">{{ $row['value_label'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                @if (! empty($r['stock_value']))
                    <tfoot>
                        @foreach ($r['stock_value'] as $total)
                            <tr class="border-t-2 font-bold">
                                <td class="p-2" colspan="3">
                                    {{ __('reports.stock_value_total', ['currency' => $total['label']]) }}
                                </td>
                                <td class="p-2">
                                    {{ \App\Support\Jalali::quantity($total['value']) }} {{ $total['label'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tfoot>
                @endif
            </table></div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
