<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ssl.intro') }}</p>

    {{-- راهنمای انتخاب حالت --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('ssl.guide_title') }}</x-slot>

        <div class="space-y-4 text-sm leading-relaxed">
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <div class="mb-1 font-bold text-primary-600 dark:text-primary-400">{{ __('ssl.mode_self') }}</div>
                <p class="text-gray-600 dark:text-gray-300">{{ __('ssl.guide_local') }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <div class="mb-1 font-bold text-primary-600 dark:text-primary-400">{{ __('ssl.mode_le') }}</div>
                <p class="text-gray-600 dark:text-gray-300">{{ __('ssl.guide_public') }}</p>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('ssl.renew_note') }}</p>
        </div>
    </x-filament::section>

    @if (! $helperInstalled)
        {{-- دستیار نصب نیست --}}
        <x-filament::section>
            <x-slot name="heading">
                <span class="text-warning-600 dark:text-warning-400">{{ __('ssl.helper_missing_title') }}</span>
            </x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('ssl.helper_missing_body') }}</p>

            <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-900 p-3 text-left text-sm text-gray-100" dir="ltr">{{ $this->getInstallCommand() }}</pre>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ __('ssl.helper_missing_note') }}</p>
        </x-filament::section>
    @else
        {{-- وضعیت فعلی --}}
        @php
            $mode  = $status['mode'] ?? 'none';
            $modeLabel = match ($mode) {
                'self-signed' => __('ssl.mode_self'),
                'letsencrypt' => __('ssl.mode_le'),
                default       => __('ssl.mode_none'),
            };
            $force = ($status['force'] ?? 'off') === 'on';
            $renew = ($status['auto_renew'] ?? '0') === '1';
        @endphp

        <x-filament::section>
            <x-slot name="heading">{{ __('ssl.status_title') }}</x-slot>

            <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('ssl.status_mode') }}</dt>
                    <dd class="font-medium">
                        @if ($mode === 'none')
                            <x-filament::badge color="gray">{{ $modeLabel }}</x-filament::badge>
                        @else
                            <x-filament::badge color="success">{{ $modeLabel }}</x-filament::badge>
                        @endif
                    </dd>
                </div>

                @if (! empty($status['server_name']) && $status['server_name'] !== '_')
                    <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('ssl.status_domain') }}</dt>
                        <dd class="font-medium" dir="ltr">{{ $status['domain'] ?: $status['server_name'] }}</dd>
                    </div>
                @endif

                @if (! empty($status['expiry']))
                    <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('ssl.status_expiry') }}</dt>
                        <dd class="font-medium" dir="ltr">{{ $status['expiry'] }}</dd>
                    </div>
                @endif

                <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('ssl.status_force') }}</dt>
                    <dd class="font-medium">
                        <x-filament::badge :color="$force ? 'success' : 'gray'">
                            {{ $force ? __('ssl.on') : __('ssl.off') }}
                        </x-filament::badge>
                    </dd>
                </div>

                @if ($mode === 'letsencrypt')
                    <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('ssl.status_renew') }}</dt>
                        <dd class="font-medium">
                            <x-filament::badge :color="$renew ? 'success' : 'warning'">
                                {{ $renew ? __('ssl.yes') : __('ssl.no') }}
                            </x-filament::badge>
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($mode === 'none')
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('ssl.force_hint') }}
                </p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
