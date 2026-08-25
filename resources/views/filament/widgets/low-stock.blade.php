<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('dashboard.low_stock')"
        icon="heroicon-o-exclamation-triangle"
    >
        @php $versions = $this->getVersions(); @endphp

        @if ($versions->isEmpty())
            <p class="text-sm text-success-600 dark:text-success-400">
                {{ __('dashboard.stock_all_good') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="p-2 text-start font-medium">{{ __('items.name') }}</th>
                            <th class="p-2 text-start font-medium">{{ __('items.version_label') }}</th>
                            <th class="p-2 text-start font-medium">{{ __('items.current_stock') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($versions as $version)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="p-2">{{ $version->item->name }}</td>
                                <td class="p-2 text-gray-600 dark:text-gray-400">{{ $version->version_code }}</td>
                                <td class="p-2">
                                    <x-filament::badge :color="$version->stock <= 0 ? 'danger' : 'warning'">
                                        {{ \App\Support\Jalali::quantity($version->stock) }}
                                        {{ $version->item->unit }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
