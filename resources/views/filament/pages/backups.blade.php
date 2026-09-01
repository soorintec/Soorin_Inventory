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

    @if ($this->canManageBackupSettings())
        @php
            $schedOn = $this->scheduleIsOn();
            $netOn   = $this->networkIsOn();
            // چراغِ وضعیت: سبز=فعال، خاکستری=خاموش.
            $lamp = fn (bool $on) => $on
                ? 'display:inline-block;width:.7rem;height:.7rem;border-radius:9999px;background:#16a34a;box-shadow:0 0 0 .18rem rgba(22,163,74,.22);'
                : 'display:inline-block;width:.7rem;height:.7rem;border-radius:9999px;background:#9ca3af;box-shadow:0 0 0 .18rem rgba(156,163,175,.20);';
            $badge = fn (bool $on) => $on
                ? 'display:inline-flex;align-items:center;gap:.3rem;padding:.15rem .6rem;border-radius:9999px;font-size:.72rem;font-weight:600;background:rgba(22,163,74,.12);color:#15803d;'
                : 'display:inline-flex;align-items:center;gap:.3rem;padding:.15rem .6rem;border-radius:9999px;font-size:.72rem;font-weight:600;background:rgba(156,163,175,.16);color:#6b7280;';
        @endphp

        <x-filament::section>
            <x-slot name="heading">{{ __('backups.auto_status') }}</x-slot>

            {{-- ساعتِ زندهٔ تهران — همان مبنایی که زمان‌بندی سرِ آن بکاپ می‌گیرد. --}}
            <div class="bk-clock">
                <span class="text-sm font-medium" style="color:#2563eb;">
                    <x-filament::icon icon="heroicon-o-clock" class="inline h-4 w-4 align-text-bottom" />
                    {{ __('backups.clock_label') }}
                </span>
                <span dir="ltr"
                      x-data="{ t: @js($this->serverClock()) }"
                      x-init="setInterval(() => { t = new Intl.DateTimeFormat('fa-IR', { timeZone: 'Asia/Tehran', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(new Date()) }, 1000)"
                      x-text="t"
                      class="font-mono text-lg font-bold tabular-nums"
                      style="letter-spacing:.06em;color:#1d4ed8;"></span>
            </div>

            {{-- هشدارِ کلیدی: اگر زمان‌بندی روشن است ولی زمان‌بندِ سرور نمی‌دود،
                 بکاپِ خودکار هرگز گرفته نمی‌شود — رایج‌ترین علتِ «بکاپ نگرفت». --}}
            @if ($schedOn && ! $this->schedulerAlive())
                <div class="mb-4 rounded-lg p-4"
                     style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.35);">
                    <div class="font-semibold" style="color:#b91c1c;">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="inline h-5 w-5 align-text-bottom" />
                        {{ __('backups.scheduler_dead_title') }}
                    </div>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ __('backups.scheduler_dead_body') }}</p>
                    <pre class="mt-2 overflow-x-auto rounded-md p-3 text-xs" dir="ltr"
                         style="background:rgba(0,0,0,.06);"><code>{{ $this->schedulerRepairCommand() }}</code></pre>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('backups.scheduler_dead_hint') }}</p>
                </div>
            @endif

            <div class="bk-grid text-sm">
                {{-- کارتِ زمان‌بندی --}}
                <div class="bk-card">
                    <div class="bk-row">
                        <span class="bk-title">
                            <span style="{{ $lamp($schedOn) }}"></span>
                            {{ __('backups.sched_section') }}
                        </span>
                        <span style="{{ $badge($schedOn) }}">
                            {{ $schedOn ? __('backups.status_active') : __('backups.status_inactive') }}
                        </span>
                    </div>

                    <div class="{{ $schedOn ? 'font-medium' : 'text-gray-400 dark:text-gray-500' }}">
                        {{ $this->scheduleSummary() ?? __('backups.sched_off') }}
                    </div>

                    @if ($schedOn && $this->lastScheduledRun())
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('backups.last_run') }}: {{ $this->lastScheduledRun() }}
                        </div>
                    @endif

                    {{-- وضعیتِ زمان‌بندِ سرور — چراغِ سلامتِ کرون --}}
                    @if ($schedOn)
                        <div class="flex items-center gap-1.5 text-xs" style="color: {{ $this->schedulerAlive() ? '#15803d' : '#b91c1c' }};">
                            <span style="{{ $lamp($this->schedulerAlive()) }}"></span>
                            {{ $this->schedulerAlive() ? __('backups.scheduler_alive') : __('backups.scheduler_dead_title') }}
                            @if ($this->schedulerHeartbeat())
                                <span class="text-gray-400">· {{ __('backups.scheduler_heartbeat') }}: {{ $this->schedulerHeartbeat() }}</span>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- کارتِ بکاپ روی شبکه --}}
                <div class="bk-card">
                    <div class="bk-row">
                        <span class="bk-title">
                            <span style="{{ $lamp($netOn) }}"></span>
                            {{ __('backups.net_section') }}
                        </span>
                        <span style="{{ $badge($netOn) }}">
                            {{ $netOn ? __('backups.status_active') : __('backups.status_inactive') }}
                        </span>
                    </div>

                    @if ($netOn)
                        <div class="break-all font-mono text-xs" dir="ltr">{{ $this->networkSummary() }}</div>
                    @else
                        <div class="text-gray-400 dark:text-gray-500">{{ __('backups.net_off') }}</div>
                    @endif
                </div>
            </div>
        </x-filament::section>
    @endif

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
