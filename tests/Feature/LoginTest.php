<?php

namespace Tests\Feature;

use App\Auth\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'کاربر تست', 'email' => 'test@yoursite.com', 'mobile' => '09121234567',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ], $attrs));
    }

    public function test_login_with_email(): void
    {
        $user = $this->user();
        $this->assertSame($user->id, (new LoginAttempt('test@yoursite.com', 'secret123'))->authenticate()->id);
    }

    public function test_login_with_mobile(): void
    {
        $user = $this->user();
        $this->assertSame($user->id, (new LoginAttempt('09121234567', 'secret123'))->authenticate()->id);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->user();
        $this->expectException(ValidationException::class);
        (new LoginAttempt('test@yoursite.com', 'wrong'))->authenticate();
    }

    public function test_inactive_account_cannot_login(): void
    {
        $this->user(['is_active' => false]);
        $this->expectException(ValidationException::class);
        (new LoginAttempt('test@yoursite.com', 'secret123'))->authenticate();
    }
}
