<?php

namespace App\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** تلاش برای ورود با ایمیل یا شماره موبایل. */
class LoginAttempt
{
    public function __construct(
        private readonly string $identifier,
        private readonly string $password,
        private readonly bool $remember = false,
    ) {}

    public function authenticate(): User
    {
        $user = $this->findUser();

        if (! $user || ! Auth::attempt($this->credentialsFor($user), $this->remember)) {
            throw ValidationException::withMessages(['identifier' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages(['identifier' => __('auth.inactive')]);
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->save();
        ActivityLog::record('login', $user);

        return $user;
    }

    private function findUser(): ?User
    {
        if (filter_var($this->identifier, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $this->identifier)->first();
        }

        return User::where('mobile', self::normalizeMobile($this->identifier))->first();
    }

    private function credentialsFor(User $user): array
    {
        return $user->email
            ? ['email' => $user->email, 'password' => $this->password]
            : ['mobile' => $user->mobile, 'password' => $this->password];
    }

    public static function normalizeMobile(string $value): string
    {
        $value = self::toEnglishDigits($value);
        $value = preg_replace('/[^0-9+]/', '', $value) ?? '';

        foreach (['+98', '0098', '98'] as $prefix) {
            if (str_starts_with($value, $prefix)) {
                $value = substr($value, strlen($prefix));
                break;
            }
        }

        $value = ltrim($value, '0');

        return $value === '' ? '' : '0' . $value;
    }

    public static function toEnglishDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
