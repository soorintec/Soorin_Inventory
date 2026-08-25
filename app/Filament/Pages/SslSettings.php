<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\SslService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * مدیریت SSL/HTTPS از داخل پنل — دو حالت: self-signed (سرور داخلی) و
 * Let's Encrypt (سرور عمومی با دامنه)، به‌علاوه اجبار HTTPS و تمدید خودکار.
 * فقط برای مدیر (مجوز تنظیمات).
 */
class SslSettings extends Page
{
    protected string $view = 'filament.pages.ssl-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?int $navigationSort = 93;

    /** وضعیت فعلی SSL (از دستیارِ سرور). */
    public array $status = [];

    public bool $helperInstalled = false;

    public static function getNavigationLabel(): string
    {
        return __('ssl.label');
    }

    public function getTitle(): string
    {
        return __('ssl.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ssl.nav_group');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageSettings->value) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $service = app(SslService::class);
        $this->helperInstalled = $service->isHelperInstalled();
        $this->status = $service->status();
    }

    /** دستور نصبِ دستیار برای نمایش در صفحه (وقتی نصب نیست) — همراه مسیر پروژه. */
    public function getInstallCommand(): string
    {
        return "cd /var/www/soorin-inventory\nsudo bash deploy/install-ssl-helper.sh";
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->selfSignedAction(),
            $this->letsEncryptAction(),
            $this->forceHttpsAction(),
            $this->disableAction(),
        ];
    }

    private function selfSignedAction(): Action
    {
        return Action::make('selfSigned')
            ->label(__('ssl.self_action'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('primary')
            ->visible(fn () => $this->helperInstalled)
            ->modalHeading(__('ssl.self_heading'))
            ->schema([
                TextInput::make('cn')
                    ->label(__('ssl.self_cn'))
                    ->helperText(__('ssl.self_cn_hint'))
                    ->default(fn () => app(SslService::class)->remembered('server_name') ?: request()->getHost())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->guardedRun(
                    fn () => app(SslService::class)->issueSelfSigned($data['cn']),
                    __('ssl.self_done'),
                );
            });
    }

    private function letsEncryptAction(): Action
    {
        return Action::make('letsEncrypt')
            ->label(__('ssl.le_action'))
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->color('gray')
            ->visible(fn () => $this->helperInstalled)
            ->modalHeading(__('ssl.le_heading'))
            ->modalDescription(__('ssl.le_warning'))
            ->schema([
                TextInput::make('domain')
                    ->label(__('ssl.le_domain'))
                    ->helperText(__('ssl.le_domain_hint'))
                    ->default(fn () => app(SslService::class)->remembered('domain'))
                    ->required(),

                TextInput::make('email')
                    ->label(__('ssl.le_email'))
                    ->helperText(__('ssl.le_email_hint'))
                    ->email()
                    ->default(fn () => app(SslService::class)->remembered('email'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->guardedRun(
                    fn () => app(SslService::class)->issueLetsEncrypt($data['domain'], $data['email']),
                    __('ssl.le_done'),
                );
            });
    }

    private function forceHttpsAction(): Action
    {
        $isOn = ($this->status['force'] ?? 'off') === 'on';

        return Action::make('forceHttps')
            ->label($isOn ? __('ssl.force_off') : __('ssl.force_on'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color($isOn ? 'warning' : 'success')
            ->visible(fn () => $this->helperInstalled && ($this->status['mode'] ?? 'none') !== 'none')
            ->requiresConfirmation()
            ->modalDescription(__('ssl.force_hint'))
            ->action(function () use ($isOn): void {
                $this->guardedRun(
                    fn () => app(SslService::class)->setForceHttps(! $isOn),
                    __('ssl.force_done'),
                );
            });
    }

    private function disableAction(): Action
    {
        return Action::make('disableSsl')
            ->label(__('ssl.disable_action'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn () => $this->helperInstalled && ($this->status['mode'] ?? 'none') !== 'none')
            ->requiresConfirmation()
            ->modalHeading(__('ssl.disable_heading'))
            ->modalDescription(__('ssl.disable_warning'))
            ->action(function (): void {
                $this->guardedRun(
                    fn () => app(SslService::class)->disable(),
                    __('ssl.disable_done'),
                );
            });
    }

    /** اجرای یک عملیات SSL با مدیریت خطا و نمایش نتیجه. */
    private function guardedRun(callable $operation, string $successMessage): void
    {
        try {
            $this->status = $operation();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title(__('ssl.failed'))
                ->body($e->getMessage())->persistent()->send();

            return;
        }

        $this->refreshStatus();

        Notification::make()->success()->title($successMessage)->persistent()->send();
    }
}
