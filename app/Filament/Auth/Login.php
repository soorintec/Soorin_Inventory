<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * صفحهٔ ورودِ سفارشی: به‌جای «ایمیل» فقط، «ایمیل یا نام کاربری» پذیرفته می‌شود.
 *
 * مقدارِ واردشده در ستونِ `email` ذخیره شده (چه ایمیل واقعی چه نام کاربری)، پس
 * فیلد همچنان `email` نام دارد و getCredentialsFromFormData بدونِ تغییر با ستونِ
 * email تطبیق می‌دهد؛ فقط اعتبارسنجیِ «باید ایمیل باشد» برداشته و برچسب عوض شده.
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('users.email_or_username'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }
}
