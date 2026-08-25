<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Permission as Perm;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    /**
     * پیشوند فیلدهای تیکِ هر گروه دسترسی.
     *
     * عمداً فیلدهای تخت و مستقل (perm_group_warehouse و…) استفاده می‌شود، نه یک
     * مسیر آرایه‌ایِ مشترک مثل permission_groups.warehouse. با مسیر مشترک،
     * چند CheckboxList روی یک والدِ آرایه‌ای می‌نشستند و در سمت مرورگر (Alpine)
     * تیک‌ها همدیگر را پاک/جابه‌جا می‌کردند و ذخیره خطا می‌داد. فیلد تخت این
     * مشکل را ندارد.
     */
    public const FIELD_PREFIX = 'perm_group_';

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('users.account'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label(__('users.name'))->required()->maxLength(255),
                    TextInput::make('email')->label(__('users.email'))->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('mobile')->label(__('users.mobile'))->maxLength(20)->unique(ignoreRecord: true),

                    TextInput::make('password')
                        ->label(__('users.password'))
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText(fn (string $operation) => $operation === 'edit' ? __('users.password_hint') : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state)),

                    Select::make('user_type')
                        ->label(__('users.user_type'))
                        ->helperText(__('users.user_type_hint'))
                        ->options(__('auth.types'))
                        ->default('staff')
                        ->required()
                        ->native(false)
                        ->live()
                        // تغییر نوع حساب، تیک‌ها را روی پیش‌فرض همان نوع می‌برد؛
                        // مدیر بعدش هر کدام را خواست عوض می‌کند.
                        ->afterStateUpdated(function ($state, $set) {
                            $defaults = Perm::splitIntoGroups(Perm::defaultsByRole()[$state] ?? []);

                            // همهٔ گروه‌ها ست می‌شوند (حتی خالی) تا گروه‌هایی که در
                            // پیش‌فرض نوع تازه نیستند هم پاک شوند.
                            foreach (array_keys(Perm::grouped()) as $group) {
                                $set(self::FIELD_PREFIX . $group, $defaults[$group] ?? []);
                            }
                        }),

                    Select::make('theme')
                        ->label(__('users.theme'))
                        ->helperText(__('users.theme_hint'))
                        ->options([
                            'ocean'  => __('common.theme_ocean'),
                            'night'  => __('common.theme_night'),
                            'system' => __('common.theme_system'),
                        ])
                        ->default('ocean')
                        ->native(false),

                    Toggle::make('is_active')
                        ->label(__('users.active'))
                        ->helperText(__('users.active_hint'))
                        ->default(true),
                ]),

            Section::make(__('users.permissions'))
                ->description(__('users.permissions_hint'))
                ->collapsible()
                ->schema(static::permissionGroups()),
        ]);
    }

    /**
     * یک فهرست تیک برای هر گروه دسترسی، هرکدام یک فیلد تختِ مستقل
     * (perm_group_warehouse و…). صفحه‌های ساخت و ویرایش این فیلدها را به یک
     * فهرست تخت مجوز تبدیل می‌کنند و بالعکس.
     *
     * @return array<int, CheckboxList>
     */
    private static function permissionGroups(): array
    {
        $labels = Perm::groupLabels();
        $fields = [];

        foreach (Perm::grouped() as $group => $options) {
            $fields[] = CheckboxList::make(self::FIELD_PREFIX . $group)
                ->label($labels[$group] ?? $group)
                ->options($options)
                ->descriptions(static::hintsFor(array_keys($options)))
                // فرم ساخت با پیش‌فرض «کارشناس» باز می‌شود، چون خودِ فیلد
                // نوع حساب هم همان را پیش‌فرض دارد.
                ->default(fn () => Perm::splitIntoGroups(Perm::defaultsByRole()['staff'] ?? [])[$group] ?? [])
                ->columns(2)
                ->bulkToggleable()
                ->columnSpanFull();
        }

        return $fields;
    }

    /**
     * @param  array<int, string>  $values
     * @return array<string, string>
     */
    private static function hintsFor(array $values): array
    {
        $hints = [];

        foreach ($values as $value) {
            $hint = Perm::from($value)->hint();

            if ($hint !== null) {
                $hints[$value] = $hint;
            }
        }

        return $hints;
    }
}
