<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\DatabaseBackupService;
use App\Support\Jalali;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
        ]);
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
            });
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
