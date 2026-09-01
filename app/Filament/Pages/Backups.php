<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\DatabaseBackupService;
use App\Services\NetworkBackupService;
use App\Support\BackupSettings;
use App\Support\Jalali;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * پشتیبان‌گیری و بازیابی دیتابیس — فقط برای مدیر.
 *
 * بازیابی عملیات ویرانگری است: داده فعلی جایش را به داده فایل می‌دهد. برای
 * همین دو قفل دارد: تیک تأیید، و پشتیبان خودکاری که سرویس پیش از بازیابی
 * می‌گیرد.
 */
class Backups extends Page
{
    protected string $view = 'filament.pages.backups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 95;

    /** @var array<int, array{name: string, size: int, created_at: \Illuminate\Support\Carbon}> */
    public array $backups = [];

    public static function getNavigationLabel(): string
    {
        return __('backups.label');
    }

    public function getTitle(): string
    {
        return __('backups.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('backups.nav_group');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * فیلامنت خودش با این متد جلوی باز شدن صفحه را می‌گیرد و ۴۰۳ می‌دهد.
     *
     * هر یک از مجوزهای پشتیبان‌گیری برای دیدن صفحه کافی است. پیش از این فقط
     * ViewBackups چک می‌شد، پس اگر مدیر به کاربری فقط «تهیه پشتیبان» می‌داد،
     * کاربر اصلاً نمی‌توانست صفحه را باز کند و پشتیبان بگیرد.
     */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyPermission([
            Permission::ViewBackups->value,
            Permission::CreateBackups->value,
            Permission::DeleteBackups->value,
            Permission::RestoreBackups->value,
            Permission::ManageBackupSettings->value,
        ]);
    }

    public function canManageBackupSettings(): bool
    {
        return auth()->user()?->can(Permission::ManageBackupSettings->value) ?? false;
    }

    public function canCreateBackups(): bool
    {
        return auth()->user()?->can(Permission::CreateBackups->value) ?? false;
    }

    public function canDeleteBackups(): bool
    {
        return auth()->user()?->can(Permission::DeleteBackups->value) ?? false;
    }

    public function canRestoreBackups(): bool
    {
        return auth()->user()?->can(Permission::RestoreBackups->value) ?? false;
    }

    public function mount(): void
    {
        $this->refreshList();
    }

    public function refreshList(): void
    {
        $this->backups = app(DatabaseBackupService::class)->list();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createAction(),
            $this->restoreAction(),
            $this->settingsAction(),
            $this->testNetworkAction(),
        ];
    }

    /** گرفتن پشتیبان تازه. */
    private function createAction(): Action
    {
        return Action::make('create')
            ->label(__('backups.create'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->visible(fn () => $this->canCreateBackups())
            ->action(function (DatabaseBackupService $service): void {
                $name = $service->create();
                $this->refreshList();

                Notification::make()
                    ->title(__('backups.created', ['file' => $name]))
                    ->success()
                    ->send();

                // اگر «بکاپ روی شبکه» روشن است، همین فایلِ تازه را آنجا هم بریز.
                if (BackupSettings::networkEnabled()) {
                    $result = app(NetworkBackupService::class)->push($service->absolutePath($name), $name);

                    Notification::make()
                        ->title($result['message'])
                        ->{$result['ok'] ? 'success' : 'warning'}()
                        ->send();
                }
            });
    }

    /**
     * تنظیماتِ «بکاپ روی شبکه» و «زمان‌بندیِ خودکار».
     *
     * دو بخش دارد: مقصدِ شبکه (پوشه + نام‌کاربری + رمز) و زمان‌بندی
     * (روزانه/هفتگی/ماهانه + ساعت). فیلدها با تیکِ فعال‌سازیِ هر بخش زنده
     * ظاهر/پنهان می‌شوند تا فرم شلوغ نباشد.
     */
    private function settingsAction(): Action
    {
        return Action::make('backupSettings')
            ->label(__('backups.settings'))
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->color('gray')
            ->visible(fn () => $this->canManageBackupSettings())
            ->modalHeading(__('backups.settings'))
            ->modalWidth('2xl')
            ->fillForm(fn () => BackupSettings::formState())
            ->schema([
                Section::make(__('backups.net_section'))
                    ->description(__('backups.net_section_hint'))
                    ->schema([
                        Toggle::make('network_enabled')
                            ->label(__('backups.net_enable'))
                            ->live(),

                        TextInput::make('network_path')
                            ->label(__('backups.net_path'))
                            ->helperText(__('backups.net_path_hint'))
                            ->placeholder('//192.168.1.10/backups/soorin')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->visible(fn ($get) => (bool) $get('network_enabled'))
                            ->required(fn ($get) => (bool) $get('network_enabled'))
                            // تستِ درجا: همین حالا با مقادیرِ واردشده دسترسیِ نوشتن را
                            // می‌آزماید — بدونِ نیاز به ذخیره. اگر یوزر/رمز غلط باشد،
                            // پیغامِ خطا را همان‌جا نشان می‌دهد.
                            ->hintAction(
                                Action::make('verifyNetwork')
                                    ->label(__('backups.test_network'))
                                    ->icon(Heroicon::OutlinedSignal)
                                    ->action(function ($get): void {
                                        // رمزِ خالی یعنی «رمزِ قبلی حفظ شود» → از تنظیماتِ ذخیره‌شده بخوان.
                                        $password = filled($get('network_password'))
                                            ? (string) $get('network_password')
                                            : BackupSettings::networkPassword();

                                        $result = app(NetworkBackupService::class)->test(
                                            (string) $get('network_path'),
                                            (string) $get('network_username'),
                                            $password,
                                        );

                                        Notification::make()
                                            ->title($result['message'])
                                            ->{$result['ok'] ? 'success' : 'danger'}()
                                            ->persistent()
                                            ->send();
                                    }),
                            ),

                        TextInput::make('network_username')
                            ->label(__('backups.net_username'))
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->visible(fn ($get) => (bool) $get('network_enabled')),

                        TextInput::make('network_password')
                            ->label(__('backups.net_password'))
                            ->helperText(__('backups.net_password_hint'))
                            ->password()
                            ->revealable()
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->visible(fn ($get) => (bool) $get('network_enabled')),

                        // وقتی کاربر بکاپِ شبکه را خاموش می‌کند و از قبل تنظیماتی
                        // ذخیره شده، می‌پرسیم نگه‌داری یا پاک. پیش‌فرض «نگه‌دار» تا
                        // دفعهٔ بعد که روشن شد لازم نباشد دوباره وارد شود.
                        Radio::make('network_on_disable')
                            ->label(__('backups.on_disable_label'))
                            ->options([
                                'keep'  => __('backups.on_disable_keep'),
                                'clear' => __('backups.on_disable_clear'),
                            ])
                            ->default('keep')
                            ->visible(fn ($get) => ! (bool) $get('network_enabled')
                                && filled(BackupSettings::networkPath())),
                    ]),

                Section::make(__('backups.sched_section'))
                    ->description(__('backups.sched_section_hint'))
                    ->schema([
                        Toggle::make('schedule_enabled')
                            ->label(__('backups.sched_enable'))
                            ->live(),

                        Select::make('schedule_frequency')
                            ->label(__('backups.sched_frequency'))
                            ->options([
                                'daily'   => __('backups.freq_daily'),
                                'weekly'  => __('backups.freq_weekly'),
                                'monthly' => __('backups.freq_monthly'),
                            ])
                            ->default('daily')
                            ->native(false)
                            ->live()
                            ->visible(fn ($get) => (bool) $get('schedule_enabled')),

                        Select::make('schedule_weekday')
                            ->label(__('backups.sched_weekday'))
                            ->options(self::weekdayOptions())
                            ->default(6)
                            ->native(false)
                            ->visible(fn ($get) => (bool) $get('schedule_enabled') && $get('schedule_frequency') === 'weekly'),

                        TextInput::make('schedule_monthday')
                            ->label(__('backups.sched_monthday'))
                            ->helperText(__('backups.sched_monthday_hint'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->default(1)
                            ->visible(fn ($get) => (bool) $get('schedule_enabled') && $get('schedule_frequency') === 'monthly'),

                        TextInput::make('schedule_time')
                            ->label(__('backups.sched_time'))
                            ->helperText(__('backups.sched_time_hint'))
                            ->placeholder('02:00')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->rule('regex:/^\d{1,2}:\d{2}$/')
                            ->visible(fn ($get) => (bool) $get('schedule_enabled')),
                    ]),
            ])
            ->action(function (array $data): void {
                BackupSettings::save($data);

                Notification::make()->success()->title(__('backups.settings_saved'))->send();
            });
    }

    /** آزمایشِ دسترسی به پوشهٔ شبکهٔ ذخیره‌شده. */
    private function testNetworkAction(): Action
    {
        return Action::make('testNetwork')
            ->label(__('backups.test_network'))
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->visible(fn () => $this->canManageBackupSettings() && BackupSettings::networkEnabled())
            ->action(function (NetworkBackupService $service): void {
                $result = $service->testSaved();

                Notification::make()
                    ->title($result['message'])
                    ->{$result['ok'] ? 'success' : 'danger'}()
                    ->persistent()
                    ->send();
            });
    }

    /** گزینه‌های روزِ هفته برای زمان‌بندیِ هفتگی (کلید = dayOfWeek کاربن). */
    private static function weekdayOptions(): array
    {
        $days = __('backups.weekdays');

        return is_array($days) ? $days : [
            0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
        ];
    }

    /** خلاصهٔ وضعیتِ زمان‌بندی برای نمایش در صفحه. */
    public function scheduleSummary(): ?string
    {
        if (! BackupSettings::scheduleEnabled()) {
            return null;
        }

        $when = match (BackupSettings::frequency()) {
            'weekly'  => __('backups.freq_weekly') . ' — ' . (self::weekdayOptions()[BackupSettings::weekday()] ?? ''),
            'monthly' => __('backups.freq_monthly') . ' — ' . __('backups.day_n', ['n' => Jalali::digits((string) BackupSettings::monthday())]),
            default   => __('backups.freq_daily'),
        };

        return $when . ' — ' . __('backups.at_time', ['time' => Jalali::digits(BackupSettings::time())]);
    }

    /** خلاصهٔ مقصدِ شبکه برای نمایش در صفحه. */
    public function networkSummary(): ?string
    {
        return BackupSettings::networkEnabled() ? BackupSettings::networkPath() : null;
    }

    public function networkIsOn(): bool
    {
        return BackupSettings::isNetworkConfigured();
    }

    public function scheduleIsOn(): bool
    {
        return BackupSettings::scheduleEnabled();
    }

    /** ساعتِ فعلیِ سرور به وقتِ تهران — همان مبنایی که زمان‌بندی با آن سنجیده می‌شود. */
    public function serverClock(): string
    {
        return Jalali::digits(\Illuminate\Support\Carbon::now(Jalali::TIMEZONE)->format('H:i:s'));
    }

    /** آخرین باری که بکاپِ خودکار اجرا شد (یا null). */
    public function lastScheduledRun(): ?string
    {
        $last = BackupSettings::lastRun();

        return $last ? Jalali::formatDateTime($last) : null;
    }

    /** آیا زمان‌بندِ سیستم‌عاملِ سرور در حال اجراست؟ (از ضربانِ هر دقیقه) */
    public function schedulerAlive(): bool
    {
        return BackupSettings::isSchedulerAlive();
    }

    /** زمانِ آخرین ضربانِ زمان‌بند برای نمایش (یا null اگر هرگز اجرا نشده). */
    public function schedulerHeartbeat(): ?string
    {
        $beat = BackupSettings::schedulerHeartbeat();

        return $beat ? Jalali::formatDateTime($beat) : null;
    }

    /** دستوری که مدیرِ سرور برای روشن‌کردنِ زمان‌بند اجرا می‌کند. */
    public function schedulerRepairCommand(): string
    {
        return 'sudo systemctl enable --now soorin-scheduler.timer';
    }

    /**
     * بازیابی از فایل آپلودی.
     *
     * تیک تأیید عمداً اجباری است — این دکمه داده فعلی را دور می‌ریزد و کاربر
     * باید صریحاً بگوید که می‌داند.
     */
    private function restoreAction(): Action
    {
        return Action::make('restore')
            ->label(__('backups.restore'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('danger')
            ->modalHeading(__('backups.restore_heading'))
            ->modalSubmitActionLabel(__('backups.restore_confirm_button'))
            ->visible(fn () => $this->canRestoreBackups())
            ->schema([
                Placeholder::make('warning')
                    ->hiddenLabel()
                    ->content(new HtmlString(
                        '<div class="text-danger-600 dark:text-danger-400 font-medium leading-relaxed">'
                        . e(__('backups.restore_warning'))
                        . '</div>',
                    )),

                // راه اول: انتخاب از پشتیبان‌های موجود روی سرور — تا کاربر مجبور
                // نباشد فایلی که همین‌جا ساخته شده را اول دانلود و دوباره آپلود کند.
                \Filament\Forms\Components\Select::make('existing')
                    ->label(__('backups.restore_existing'))
                    ->helperText(__('backups.restore_existing_hint'))
                    ->options(fn () => collect(app(DatabaseBackupService::class)->list())
                        ->mapWithKeys(fn (array $b) => [$b['name'] => $b['name'] . ' — ' . $this->humanSize($b['size'])]))
                    ->searchable()
                    ->native(false)
                    ->placeholder(__('backups.restore_existing_placeholder')),

                // راه دوم: آپلود فایل. بدون محدودیت نوع فایل، چون پسوند .sql روی
                // ویندوز به mime استانداردی نگاشت نمی‌شود و پنجره انتخاب فقط
                // .txt نشان می‌داد؛ اعتبارسنجی پسوند در خود اکشن انجام می‌شود.
                FileUpload::make('file')
                    ->label(__('backups.restore_file'))
                    ->helperText(__('backups.restore_file_hint'))
                    ->preserveFilenames()
                    ->storeFiles(false),

                Checkbox::make('understood')
                    ->label(__('backups.restore_understood'))
                    ->accepted()
                    ->required(),
            ])
            ->action(function (array $data, DatabaseBackupService $service): void {
                // مبدأ بازیابی: یا پشتیبان موجود روی سرور، یا فایل آپلودی.
                $path = null;

                if (filled($data['existing'] ?? null) && $service->exists($data['existing'])) {
                    $path = $service->absolutePath($data['existing']);
                } elseif (! empty($data['file'])) {
                    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile $upload */
                    $upload = $data['file'];
                    $extension = strtolower($upload->getClientOriginalExtension());

                    if (! in_array($extension, ['sql', 'txt'], true)) {
                        Notification::make()->title(__('backups.restore_bad_type'))->danger()->send();

                        return;
                    }

                    $path = $upload->getRealPath();
                }

                if ($path === null) {
                    Notification::make()->title(__('backups.restore_no_source'))->danger()->send();

                    return;
                }

                try {
                    $safety = $service->restore($path);
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title(__('backups.restore_failed'))
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $this->refreshList();

                Notification::make()
                    ->title(__('backups.restored'))
                    // اگر دیتابیس خالی بوده، پشتیبان ایمنی گرفته نشده و
                    // اشاره به فایلی که وجود ندارد گمراه‌کننده است.
                    ->body($safety
                        ? __('backups.restored_safety', ['file' => $safety])
                        : __('backups.restored_no_safety'))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    /** دانلود یک فایل پشتیبان. */
    public function download(string $name): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can(Permission::ViewBackups->value), 403);

        $service = app(DatabaseBackupService::class);

        abort_unless($service->exists($name), 404);

        return response()->download($service->absolutePath($name));
    }

    public function deleteBackup(string $name): void
    {
        abort_unless(auth()->user()?->can(Permission::DeleteBackups->value), 403);

        app(DatabaseBackupService::class)->delete($name);
        $this->refreshList();

        Notification::make()->title(__('backups.deleted'))->success()->send();
    }

    /** حجم خوانا: «۱٫۲ مگابایت» */
    public function humanSize(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return Jalali::digits(number_format($bytes / 1_048_576, 1, '٫', '٬')) . ' ' . __('backups.megabyte');
        }

        return Jalali::digits(number_format(max($bytes / 1024, 0.1), 1, '٫', '٬')) . ' ' . __('backups.kilobyte');
    }
}
