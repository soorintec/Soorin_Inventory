<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('activity.recent')"
        icon="heroicon-o-clock"
        collapsible
    >
        @php
            $activities = $this->getActivities();
            $tints = [
                'in'        => 'rgba(16,185,129,0.10)',
                'out'       => 'rgba(239,68,68,0.10)',
                'edit'      => 'rgba(234,179,8,0.14)',
                'backup'    => 'rgba(59,130,246,0.11)',
                'stocktake' => 'rgba(168,85,247,0.12)',
            ];
        @endphp

        @if ($activities->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('activity.empty') }}</p>
        @else
            {{-- روی موبایل جدول باید افقی اسکرول شود، نه اینکه صفحه را بشکند --}}
            {{-- فاصله ستون‌ها زیاد گرفته شده (px-5) تا زمان، کاربر، عمل و موضوع
                 به هم نچسبند؛ پیش از این خیلی نزدیک هم بودند. --}}
            <div class="calc-parts-scroll overflow-x-auto">
                <table class="dash-table w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-start font-medium whitespace-nowrap">{{ __('activity.when') }}</th>
                            <th class="text-start font-medium whitespace-nowrap">{{ __('activity.user') }}</th>
                            <th class="text-start font-medium whitespace-nowrap">{{ __('activity.action') }}</th>
                            <th class="text-start font-medium">{{ __('activity.subject') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activities as $activity)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800"
                                style="background: {{ $tints[$activity->colorGroup()] ?? 'transparent' }}">
                                <td class="whitespace-nowrap text-gray-600 dark:text-gray-400">
                                    {{ $activity->happenedAt() }}
                                </td>
                                <td class="whitespace-nowrap">
                                    {{ $activity->user?->name ?? __('activity.system') }}
                                </td>
                                <td>
                                    <x-filament::badge color="gray">
                                        {{ $activity->actionLabel() }}
                                    </x-filament::badge>
                                </td>
                                <td class="text-gray-600 dark:text-gray-400">
                                    {{ $activity->subjectLabel() ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
