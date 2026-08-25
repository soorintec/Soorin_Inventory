<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('branding.intro') }}</p>

    {{-- پیش‌نمایش وضعیت ذخیره‌شدهٔ فعلی (پس از ذخیره، صفحه نو می‌شود و این هم تازه می‌شود) --}}
    @php
        $navColor  = config('branding.themes.ocean.nav');
        $navText   = config('branding.themes.ocean.nav_text');
        $year      = \App\Support\Jalali::digits((string) \App\Support\Branding::foundedYear())
            . ' – ' . \App\Support\Jalali::digits(verta()->format('Y'));
    @endphp

    <x-filament::section>
        <x-slot name="heading">{{ __('branding.preview') }}</x-slot>
        <x-slot name="description">{{ __('branding.preview_hint') }}</x-slot>

        <div class="flex flex-col gap-4">
            {{-- نوار بالا: لوگوی تیره روی پس‌زمینهٔ سرمه‌ای --}}
            <div class="flex items-center gap-3 rounded-xl px-5 py-4"
                 style="background: {{ $navColor }};">
                <img src="{{ \App\Support\Branding::logo('dark') }}" alt="" style="height: 2.75rem;">
                <span class="text-base font-bold" style="color: #ffffff;">
                    {{ \App\Support\Branding::appTitle() }}
                </span>
            </div>

            {{-- نوار بالا روی پس‌زمینهٔ روشن + نشان مربعی --}}
            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <img src="{{ \App\Support\Branding::logo('light') }}" alt="" style="height: 2.75rem;">
                    <span class="text-base font-bold text-gray-800">
                        {{ \App\Support\Branding::appTitle() }}
                    </span>
                </div>
                <img src="{{ \App\Support\Branding::logo('mark') }}" alt="" style="height: 2.25rem;">
            </div>

            {{-- فوتر --}}
            <div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-3 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                © {{ $year }} —
                {{ __('branding.preview_footer', ['name' => \App\Support\Branding::companyName()]) }}
                <span class="mx-2 text-gray-300">·</span>
                <span dir="ltr">{{ \App\Support\Branding::websiteLabel() }}</span>
            </div>
        </div>
    </x-filament::section>

    <form wire:submit.prevent="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check" size="lg">
                {{ __('branding.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
