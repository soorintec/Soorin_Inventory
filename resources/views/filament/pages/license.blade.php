<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('license.intro') }}</p>

    @php $s = $this->status; $pay = $this->payment(); @endphp

    {{-- وضعیت --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('license.status_title') }}</x-slot>

        @if ($s['licensed'] ?? false)
            <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('license.status_title') }}</dt>
                    <dd><x-filament::badge color="success">{{ __('license.status_licensed') }}</x-filament::badge></dd>
                </div>
                <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('license.licensed_to') }}</dt>
                    <dd class="font-medium">{{ $s['licensed_to'] ?: '—' }}</dd>
                </div>
                <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('license.hwid') }}</dt>
                    <dd class="font-medium" dir="ltr">{{ $s['hwid'] ?: __('license.transferable') }}</dd>
                </div>
                <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('license.expires') }}</dt>
                    <dd class="font-medium">{{ $s['expires_at'] ? \App\Support\Jalali::format($s['expires_at']) : __('license.perpetual') }}</dd>
                </div>
            </dl>
        @else
            @if ($s['locked'] ?? false)
                @php $clock = $s['clock_tampered'] ?? false; @endphp
                <div class="rounded-lg border border-danger-300 bg-danger-50 p-4 text-sm dark:border-danger-700 dark:bg-danger-950/40">
                    <div class="mb-1 font-bold text-danger-700 dark:text-danger-400">
                        {{ $clock ? __('license.clock_title') : __('license.locked_title') }}
                    </div>
                    <p class="text-danger-700 dark:text-danger-300">
                        {{ $clock ? __('license.clock_body') : __('license.locked_body') }}
                    </p>
                </div>
            @else
                <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm dark:border-warning-700 dark:bg-warning-950/40">
                    <div class="mb-1 font-bold text-warning-700 dark:text-warning-400">{{ __('license.grace_title') }}</div>
                    <p class="text-warning-700 dark:text-warning-300">
                        {{ __('license.grace_left', ['days' => \App\Support\Jalali::digits((string) ($s['grace_days_left'] ?? 0))]) }}
                    </p>
                </div>
            @endif

            {{-- شناسهٔ سخت‌افزار — کاربر این را برای فروشنده می‌فرستد --}}
            <div class="mt-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <div class="mb-2 text-sm font-semibold">{{ __('license.this_hwid') }}</div>
                <code class="block overflow-x-auto rounded bg-gray-100 p-3 text-center font-mono text-base font-bold tracking-wider dark:bg-gray-800" dir="ltr">{{ \App\Support\License::hardwareId() }}</code>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('license.copy_hint') }}</p>
            </div>
        @endif
    </x-filament::section>

    {{-- واردکردن کلید --}}
    @unless ($s['licensed'] ?? false)
        <x-filament::section>
            <x-slot name="heading">{{ __('license.enter_title') }}</x-slot>

            <form wire:submit.prevent="activate">
                {{ $this->form }}

                @if ($this->canManage())
                    <div class="mt-4">
                        <x-filament::button type="submit" icon="heroicon-o-key">
                            {{ __('license.activate') }}
                        </x-filament::button>
                    </div>
                @endif
            </form>
        </x-filament::section>

        {{-- خرید --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('license.purchase_title') }}</x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('license.purchase_intro') }}</p>

            @if (empty($pay['usdt_address']) && empty($pay['contact']))
                <p class="mt-3 text-sm text-gray-400">{{ __('license.no_payment_info') }}</p>
            @else
                <div class="mt-4 space-y-3 text-sm">
                    @if (! empty($pay['price']))
                        <div class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('license.price') }}</span>
                            <span class="font-bold">{{ $pay['price'] }}</span>
                        </div>
                    @endif
                    @if (! empty($pay['usdt_address']))
                        <div class="border-b border-gray-100 pb-2 dark:border-gray-800">
                            <div class="mb-1 text-gray-500 dark:text-gray-400">{{ __('license.usdt_address') }}
                                @if (! empty($pay['usdt_network'])) <span class="text-xs">({{ $pay['usdt_network'] }})</span> @endif
                            </div>
                            <code class="block overflow-x-auto rounded bg-gray-100 p-3 font-mono text-sm font-bold dark:bg-gray-800" dir="ltr">{{ $pay['usdt_address'] }}</code>
                        </div>
                    @endif
                </div>

                <div class="mt-5">
                    <div class="mb-2 text-sm font-semibold">{{ __('license.steps_title') }}</div>
                    <ol class="list-inside list-decimal space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        <li>{{ __('license.step_pay') }}</li>
                        <li>{{ __('license.step_send') }}</li>
                        <li>{{ __('license.step_receive') }}</li>
                    </ol>
                </div>
            @endif
        </x-filament::section>
    @endunless

    {{-- درباره و حمایت — همیشه دیده می‌شود تا کاربر بتواند حمایت/دونیت کند --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('license.about_title') }}</x-slot>

        <img src="{{ asset('images/soorin-banner.png') }}" alt="Soorin Inventory"
             class="mx-auto mb-5 w-full max-w-xl rounded-xl shadow-sm">

        <div class="space-y-3 text-sm leading-relaxed">
            <p class="text-gray-600 dark:text-gray-300">{{ __('license.about_body') }}</p>

            <div class="rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-950/30">
                <p class="font-medium text-primary-800 dark:text-primary-300">{{ __('license.pricing_note') }}</p>
            </div>

            @if (! empty($pay['usdt_address']))
                <div>
                    <div class="mb-1 text-gray-500 dark:text-gray-400">
                        {{ __('license.usdt_address') }}
                        @if (! empty($pay['usdt_network'])) <span class="text-xs">({{ $pay['usdt_network'] }})</span> @endif
                    </div>
                    <code class="block overflow-x-auto rounded bg-gray-100 p-3 font-mono text-sm font-bold dark:bg-gray-800" dir="ltr">{{ $pay['usdt_address'] }}</code>
                </div>
            @endif

            <p class="text-gray-600 dark:text-gray-300">{{ __('license.donate_note') }}</p>

            @if (! empty($pay['contact']))
                @php
                    $c = $pay['contact'];
                    $isUrl = \Illuminate\Support\Str::startsWith($c, ['http://', 'https://']);
                    $isTelegram = \Illuminate\Support\Str::contains($c, 't.me');
                    $handle = $isTelegram ? '@' . \Illuminate\Support\Str::afterLast(rtrim($c, '/'), '/') : preg_replace('#^https?://#', '', $c);
                    $href = $isUrl ? $c : ($isTelegram ? 'https://' . ltrim($c, '/') : null);
                @endphp
                <div class="mt-4 flex flex-col items-center gap-2 border-t border-gray-100 pt-5 dark:border-gray-800">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('license.contact') }}</span>
                    <a href="{{ $href ?? '#' }}" @if ($href) target="_blank" rel="noopener" @endif
                       class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:opacity-90"
                       style="background:#229ED9" dir="ltr">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/>
                        </svg>
                        <span>{{ $handle }}</span>
                    </a>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
