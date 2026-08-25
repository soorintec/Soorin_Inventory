<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('updates.current_version') }}</x-slot>

        <div class="flex flex-wrap items-center gap-3">
            <span class="text-2xl font-bold" dir="ltr">{{ $status['current'] ?? '—' }}</span>

            @if (($status['method'] ?? null) === 'offline')
                <x-filament::badge color="gray">{{ __('updates.offline_mode') }}</x-filament::badge>
            @endif
        </div>

        @if (! empty($status['checked']))
            <div class="mt-4">
                @if (! empty($status['error']))
                    <p class="text-sm text-warning-600 dark:text-warning-400">
                        {{ __('updates.check_failed') }} — {{ $status['error'] }}
                    </p>
                @elseif (! empty($status['available']))
                    <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                        {{ __('updates.available', ['version' => $status['latest']]) }}
                    </p>
                @else
                    <p class="text-sm font-medium text-success-600 dark:text-success-400">
                        {{ __('updates.up_to_date') }}
                    </p>
                @endif
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('updates.how_it_works') }}</x-slot>

        <ul class="list-disc space-y-1 pe-5 text-sm text-gray-600 dark:text-gray-400">
            <li>{{ __('updates.hint_check') }}</li>
            <li>{{ __('updates.hint_git') }}</li>
            <li>{{ __('updates.hint_zip') }}</li>
            <li>{{ __('updates.hint_backup') }}</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
