<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\Setting;
use App\Support\Branding;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

/**
 * شخصی‌سازی سامانه — مدیر نام کسب‌وکار، عنوان، اطلاعات تماس و لوگوها را عوض
 * می‌کند. مقادیر در جدول settings (گروه branding) ذخیره و توسط App\Support\Branding
 * در سراسر سامانه خوانده می‌شوند. فقط برای مدیر (مجوز تنظیمات).
 */
class BrandingSettings extends Page
{
    protected string $view = 'filament.pages.branding-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 94;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** واریانت‌های لوگو که فیلد فایل دارند. */
    private const LOGO_FIELDS = ['logo_light', 'logo_dark', 'logo_mark', 'favicon'];

    public static function getNavigationLabel(): string
    {
        return __('branding.label');
    }

    public function getTitle(): string
    {
        return __('branding.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('branding.nav_group');
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

        // فیلدهای متنی از Branding (با پیش‌فرض)، فیلدهای فایل از مسیرِ خامِ ذخیره‌شده
        // تا فیلامنت فایلِ موجود را نشان دهد؛ اگر تنظیم نشده باشد خالی می‌ماند و
        // فیلد، پیش‌نمایشِ فایلِ پیش‌فرض را نشان نمی‌دهد (که درست است، آن فایلِ ما نیست).
        $state = Branding::formState();

        foreach (self::LOGO_FIELDS as $field) {
            $state[$field] = Setting::get(Branding::GROUP . '.' . $field);
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('branding.identity_section'))
                    ->description(__('branding.identity_section_hint'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label(__('branding.company_name'))
                            ->helperText(__('branding.company_name_hint'))
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),

                        TextInput::make('app_title')
                            ->label(__('branding.app_title'))
                            ->helperText(__('branding.app_title_hint'))
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),

                        TextInput::make('company_name_en')
                            ->label(__('branding.company_name_en'))
                            ->helperText(__('branding.company_name_en_hint'))
                            ->maxLength(150),

                        TextInput::make('founded_year')
                            ->label(__('branding.founded_year'))
                            ->helperText(__('branding.founded_year_hint'))
                            ->numeric()
                            ->minValue(1000)
                            ->maxValue(2200),

                        TextInput::make('website')
                            ->label(__('branding.website'))
                            ->helperText(__('branding.website_hint'))
                            ->url()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::OutlinedGlobeAlt),

                        TextInput::make('website_label')
                            ->label(__('branding.website_label'))
                            ->helperText(__('branding.website_label_hint'))
                            ->maxLength(100),
                    ]),

                Section::make(__('branding.contact_section'))
                    ->description(__('branding.contact_section_hint'))
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextInput::make('phone')
                            ->label(__('branding.phone'))
                            ->tel()
                            ->maxLength(50),

                        Textarea::make('address')
                            ->label(__('branding.address'))
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('branding.logo_section'))
                    ->description(__('branding.logo_section_hint'))
                    ->columns(2)
                    ->schema([
                        $this->logoField('logo_light', __('branding.logo_light'), __('branding.logo_light_hint')),
                        $this->logoField('logo_dark', __('branding.logo_dark'), __('branding.logo_dark_hint')),
                        $this->logoField('logo_mark', __('branding.logo_mark'), __('branding.logo_mark_hint')),
                        $this->logoField('favicon', __('branding.favicon'), __('branding.favicon_hint'), image: true),
                    ]),
            ]);
    }

    /**
     * فیلد آپلود لوگو روی دیسک branding (public/branding/logos). عمداً ->image()
     * نمی‌زنیم چون ویرایشگر تصویرِ فیلامنت SVG را پشتیبانی نمی‌کند و لوگوهای
     * پیش‌فرض SVG هستند؛ نوع فایل را دستی محدود می‌کنیم.
     */
    private function logoField(string $name, string $label, string $hint, bool $image = false): FileUpload
    {
        $types = $image
            ? ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon']
            : ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'];

        return FileUpload::make($name)
            ->label($label)
            ->helperText($hint)
            ->disk(Branding::DISK)
            ->directory('logos')
            ->visibility('public')
            ->acceptedFileTypes($types)
            ->maxSize(2048)
            ->downloadable()
            ->openable()
            ->deletable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('branding.save'))
                ->icon(Heroicon::OutlinedCheck)
                ->action('save'),

            Action::make('reset')
                ->label(__('branding.reset'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('branding.reset_confirm'))
                ->action('resetToDefaults'),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        // فیلدهای متنی
        Setting::set(Branding::GROUP . '.company_name', $data['company_name'] ?? '', Branding::GROUP);
        Setting::set(Branding::GROUP . '.company_name_en', $data['company_name_en'] ?? '', Branding::GROUP);
        Setting::set(Branding::GROUP . '.app_title', $data['app_title'] ?? '', Branding::GROUP);
        Setting::set(Branding::GROUP . '.website', $data['website'] ?? '', Branding::GROUP);
        Setting::set(Branding::GROUP . '.website_label', $data['website_label'] ?? '', Branding::GROUP);
        Setting::set(Branding::GROUP . '.founded_year', (int) ($data['founded_year'] ?? 0), Branding::GROUP, 'int');
        Setting::set(Branding::GROUP . '.phone', $data['phone'] ?? '', Branding::GROUP);
        Setting::set(Branding::GROUP . '.address', $data['address'] ?? '', Branding::GROUP);

        // فیلدهای فایل — مقدار، مسیرِ فایلِ ذخیره‌شده روی دیسک است (یا خالی اگر حذف شده).
        foreach (self::LOGO_FIELDS as $field) {
            $path = $this->normalizePath($data[$field] ?? null);
            Setting::set(Branding::GROUP . '.' . $field, $path ?? '', Branding::GROUP, 'file');
        }

        Notification::make()
            ->success()
            ->title(__('branding.saved'))
            ->body(__('branding.saved_body'))
            ->send();

        // لوگو و نامِ نوار بالا در همان بارگذاری فعلی رندر شده‌اند؛ نو کردن صفحه
        // تا مقادیر تازه همه‌جا (از جمله <head> فیلامنت) بنشیند.
        $this->redirect(static::getUrl());
    }

    public function resetToDefaults(): void
    {
        abort_unless(static::canAccess(), 403);

        // فایل‌های آپلودی پاک می‌شوند تا فضای دیسک اشغال نماند.
        foreach (self::LOGO_FIELDS as $field) {
            $path = Setting::get(Branding::GROUP . '.' . $field);

            if (filled($path) && Storage::disk(Branding::DISK)->exists($path)) {
                Storage::disk(Branding::DISK)->delete($path);
            }
        }

        // حذفِ تک‌تک (نه mass-delete) تا رویدادِ deletedِ مدل، کشِ هر کلید را هم پاک کند.
        Setting::where('group', Branding::GROUP)->get()->each->delete();

        Notification::make()->success()->title(__('branding.reset_done'))->send();

        $this->redirect(static::getUrl());
    }

    /** مقدار فیلد FileUpload می‌تواند رشته یا آرایه باشد؛ به یک مسیر تخت تبدیلش می‌کند. */
    private function normalizePath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return filled($value) ? (string) $value : null;
    }
}
