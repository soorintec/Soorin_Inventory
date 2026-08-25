<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('activity.recent')"
        icon="heroicon-o-clock"
        collapsible
    >
        @php $activities = $this->getActivities(); @endphp

        @if ($activities->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('activity.empty') }}</p>
        @else
            {{-- روی موبایل جدول باید افقی اسکرول شود، نه اینکه صفحه را بشکند --}}
            {{-- فاصله ستون‌ها زیاد گرفته شده (px-5) تا زمان، کاربر، عمل و موضوع
                 به هم نچسبند؛ پیش از این خیلی نزدیک هم بودند. --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-5 py-2.5 text-start font-medium whitespace-nowrap">{{ __('activity.when') }}</th>
                            <th class="px-5 py-2.5 text-start font-medium whitespace-nowrap">{{ __('activity.user') }}</th>
                            <th class="px-5 py-2.5 text-start font-medium whitespace-nowrap">{{ __('activity.action') }}</th>
                            <th class="px-5 py-2.5 text-start font-medium">{{ __('activity.subject') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activities as $activity)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="whitespace-nowrap px-5 py-2.5 text-gray-600 dark:text-gray-400">
                                    {{ $activity->happenedAt() }}
                                </td>
                                <td class="px-5 py-2.5 whitespace-nowrap">
                                    {{ $activity->user?->name ?? __('activity.system') }}
                                </td>
                                <td class="px-5 py-2.5">
                                    <x-filament::badge color="gray">
                                        {{ $activity->actionLabel() }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-5 py-2.5 text-gray-600 dark:text-gray-400">
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
