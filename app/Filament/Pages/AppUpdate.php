<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\AppUpdateService;
use App\Support\AppVersion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * به‌روزرسانی خودِ برنامه از داخل پنل — چک نسخه، آپدیت از گیت‌هاب یا با فایل زیپ.
 * فقط برای مدیر (مجوز تنظیمات).
 */
class AppUpdate extends Page
{
    protected string $view = 'filament.pages.app-update';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?int $navigationSort = 96;

    /** نتیجهٔ آخرین بررسی به‌روزرسانی. */
    public array $status = [];

    public static function getNavigationLabel(): string
    {
        return __('updates.label');
    }

    public function getTitle(): string
    {
        return __('updates.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('backups.nav_group');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** نشانِ قرمزِ «نسخهٔ جدید» کنار آیتم منو — از کشِ بررسیِ روزانه می‌خواند. */
    public static function getNavigationBadge(): ?string
    {
        return app(AppUpdateService::class)->availableUpdate();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return app(AppUpdateService::class)->availableUpdate()
            ? __('updates.badge_tooltip')
            : null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageSettings->value) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        // نتیجهٔ آخرین بررسیِ روزانه (کش) را نشان می‌دهیم تا کاربر بدون کلیک هم
        // بداند نسخهٔ جدیدی هست؛ اگر کشی نبود، فقط نسخهٔ فعلی. بررسیِ زندهٔ گیت‌هاب
        // با دکمهٔ «بررسی به‌روزرسانی» انجام می‌شود تا صفحه معطل شبکه نماند.
        $cached = app(AppUpdateService::class)->cached();

        $this->status = $cached !== [] ? ($cached + ['checked' => true]) : [
            'method'    => AppVersion::isGitRepo() ? 'git' : 'offline',
            'current'   => AppVersion::current(),
            'latest'    => null,
            'available' => false,
            'checked'   => false,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->checkAction(),
            $this->updateFromGitAction(),
            $this->updateFromZipAction(),
            $this->linkToGitAction(),
        ];
    }

    /**
     * اتصال یک نصبِ زیپی (بدون .git) به گیت‌هاب تا از آن پس آپدیت گیت‌هاب کار کند.
     * فقط وقتی دیده می‌شود که استقرار هنوز مخزن گیت نیست.
     */
    private function linkToGitAction(): Action
    {
        return Action::make('linkGit')
            ->label(__('updates.link_git'))
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->visible(fn () => ($this->status['method'] ?? null) === 'offline')
            ->modalHeading(__('updates.link_git'))
            ->modalDescription(__('updates.link_git_warning'))
            ->schema([
                \Filament\Forms\Components\TextInput::make('url')
                    ->label(__('updates.link_git_url'))
                    ->helperText(__('updates.link_git_url_hint'))
                    ->default(config('branding.github.repo'))
                    ->required()
                    ->url(),
            ])
            ->action(function (array $data, AppUpdateService $service): void {
                try {
                    $result = $service->linkToGit($data['url']);
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title(__('updates.link_git_failed'))
                        ->body($e->getMessage())->persistent()->send();

                    return;
                }

                // از این پس استقرار، گیت است.
                $this->status['method'] = 'git';
                $this->status['current'] = $result['version'];

                Notification::make()->success()
                    ->title(__('updates.link_git_done'))
                    ->body(__('updates.updated_backup', ['file' => $result['backup'] ?? '—']))
                    ->persistent()->send();
            });
    }

    private function checkAction(): Action
    {
        return Action::make('check')
            ->label(__('updates.check'))
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->action(function (AppUpdateService $service): void {
                // بررسیِ زنده + به‌روزرسانیِ کش تا نشانِ قرمزِ منو هم فوراً هماهنگ شود.
                $this->status = $service->refreshCache() + ['checked' => true];

                if (! empty($this->status['error'])) {
                    Notification::make()->warning()->title(__('updates.check_failed'))
                        ->body($this->status['error'])->send();

                    return;
                }

                if ($this->status['available']) {
                    Notification::make()->success()
                        ->title(__('updates.available', ['version' => $this->status['latest']]))->send();
                } else {
                    Notification::make()->success()->title(__('updates.up_to_date'))->send();
                }
            });
    }

    private function updateFromGitAction(): Action
    {
        return Action::make('updateGit')
            ->label(__('updates.update_git'))
            ->icon(Heroicon::OutlinedCloudArrowDown)
            ->color('primary')
            ->visible(fn () => ($this->status['method'] ?? null) === 'git' && ($this->status['available'] ?? false))
            ->requiresConfirmation()
            ->modalHeading(__('updates.update_git'))
            ->modalDescription(__('updates.update_warning'))
            ->action(function (AppUpdateService $service): void {
                try {
                    $result = $service->updateFromGit();
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title(__('updates.update_failed'))
                        ->body($e->getMessage())->persistent()->send();

                    return;
                }

                $this->status['current'] = $result['version'];
                $this->status['available'] = false;

                Notification::make()->success()
                    ->title(__('updates.updated', ['version' => $result['version']]))
                    ->body(__('updates.updated_backup', ['file' => $result['backup'] ?? '—']))
                    ->persistent()->send();
            });
    }

    private function updateFromZipAction(): Action
    {
        return Action::make('updateZip')
            ->label(__('updates.update_zip'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->modalHeading(__('updates.update_zip'))
            ->modalDescription(__('updates.update_warning'))
            ->schema([
                FileUpload::make('file')
                    ->label(__('updates.zip_file'))
                    ->helperText(__('updates.zip_hint'))
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data, AppUpdateService $service): void {
                /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile $upload */
                $upload = $data['file'];

                if (strtolower($upload->getClientOriginalExtension()) !== 'zip') {
                    Notification::make()->danger()->title(__('updates.zip_bad_type'))->send();

                    return;
                }

                try {
                    $result = $service->updateFromZip($upload->getRealPath());
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title(__('updates.update_failed'))
                        ->body($e->getMessage())->persistent()->send();

                    return;
                }

                $this->status['current'] = $result['version'];
                $this->status['available'] = false;

                Notification::make()->success()
                    ->title(__('updates.updated', ['version' => $result['version']]))
                    ->body(__('updates.updated_backup', ['file' => $result['backup'] ?? '—']))
                    ->persistent()->send();
            });
    }
}
