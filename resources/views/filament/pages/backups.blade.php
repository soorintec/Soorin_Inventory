<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('backups.how_it_works') }}</x-slot>

        <ul class="list-disc space-y-1 pe-5 text-sm text-gray-600 dark:text-gray-400">
            <li>{{ __('backups.hint_create') }}</li>
            <li>{{ __('backups.hint_keep') }}</li>
            <li>{{ __('backups.hint_restore') }}</li>
            <li>{{ __('backups.hint_portable') }}</li>
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('backups.list') }}</x-slot>

        @if (empty($backups))
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('backups.empty') }}</p>
        @else
            {{-- چیدمان کارتی و واکنش‌گرا به‌جای جدول عریض: روی گوشی نام فایل در
                 چند خط می‌شکند و دکمه‌های دانلود/حذف زیرش می‌آیند و در دسترس‌اند؛
                 روی دسکتاپ همه در یک ردیف. --}}
            <div class="space-y-3">
                @foreach ($backups as $backup)
                    <div class="flex flex-col gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="break-all font-mono text-xs" dir="ltr">{{ $backup['name'] }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ \App\Support\Jalali::formatDateTime($backup['created_at']) }}
                                &nbsp;·&nbsp;
                                {{ $this->humanSize($backup['size']) }}
                            </div>
                        </div>

                        {{--
                            نام فایل با {{ }} داخل رشته می‌آید، نه با @js — @js داخل
                            attribute کامپوننت Blade کامپایل نمی‌شود. نام در سرویس با
                            الگوی سخت‌گیرانه اعتبارسنجی شده، پس نقل‌قول داخلش راه ندارد.
                        --}}
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <x-filament::button
                                size="xs"
                                color="gray"
                                icon="heroicon-o-arrow-down-tray"
                                tag="a"
                                :href="route('backups.download', ['name' => $backup['name']])"
                            >
                                {{ __('backups.download') }}
                            </x-filament::button>

                            @if ($this->canDeleteBackups())
                                <x-filament::button
                                    size="xs"
                                    color="danger"
                                    icon="heroicon-o-trash"
                                    wire:click="deleteBackup('{{ $backup['name'] }}')"
                                    wire:confirm="{{ __('backups.delete_confirm') }}"
                                >
                                    {{ __('backups.delete') }}
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
