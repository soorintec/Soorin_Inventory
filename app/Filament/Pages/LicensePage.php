<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Support\License;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * صفحهٔ لایسنس — نمایش وضعیت، واردکردن کلید، و اطلاعات خرید (ولت تتر).
 *
 * برای همهٔ کاربران واردشده قابل دسترسی است (تا وقتی پنل قفل می‌شود، کاربر به
 * همین صفحه هدایت شود)، ولی فقط مدیر (مجوز تنظیمات) می‌تواند کلید را ثبت کند.
 */
class LicensePage extends Page
{
    protected string $view = 'filament.pages.license';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 98;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public array $status = [];

    public static function getNavigationLabel(): string
    {
        return __('license.label');
    }

    public function getTitle(): string
    {
        return __('license.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('license.nav_group');
    }

    /** فقط مدیر در منو می‌بیندش؛ ولی صفحه برای همه (هدف هدایتِ قفل) باز است. */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can(Permission::ManageSettings->value) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        $this->status = License::status();
        $this->form->fill();
    }

    public function canManage(): bool
    {
        return auth()->user()?->can(Permission::ManageSettings->value) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Textarea::make('key')
                    ->label(__('license.key_label'))
                    ->helperText(__('license.key_hint'))
                    ->rows(3)
                    ->required()
                    ->disabled(fn () => ! $this->canManage()),
            ]);
    }

    public function activate(): void
    {
        abort_unless($this->canManage(), 403);

        $key = trim((string) ($this->form->getState()['key'] ?? ''));
        $check = License::verify($key);

        if (! $check['valid']) {
            $reason = __('license.reasons.' . ($check['reason'] ?? 'malformed'));

            Notification::make()->danger()
                ->title(__('license.invalid'))
                ->body(is_string($reason) ? $reason : '')
                ->persistent()->send();

            return;
        }

        License::store($key);
        $this->status = License::status();

        Notification::make()->success()->title(__('license.activated'))->persistent()->send();

        $this->redirect(static::getUrl());
    }

    /** اطلاعات پرداختِ فروشنده برای نمایش. */
    public function payment(): array
    {
        return (array) config('license.payment');
    }
}
