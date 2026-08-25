<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ثبت «آخرین ورود» روی رویداد ورود لاراول.
 *
 * پنل از فرم ورود پیش‌فرض فیلامنت استفاده می‌کند، پس last_login_at باید با
 * شنیدن رویداد Login ثبت شود، نه با کلاس LoginAttempt که هیچ‌وقت صدا زده
 * نمی‌شد و ویجت «آخرین ورودها» را همیشه خالی نگه می‌داشت.
 */
class LoginRecordsLastLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_event_stamps_last_login_at(): void
    {
        $user = User::create([
            'name' => 'کاربر', 'email' => 'u@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);

        $this->assertNull($user->last_login_at);

        event(new Login('web', $user, false));

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_it_does_not_reset_permissions_on_login(): void
    {
        $user = User::create([
            'name' => 'کارشناس', 'email' => 's@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        // مجوز دلخواه که مدیر داده باشد
        $user->syncPermissions([\App\Enums\Permission::ManageStocktakes->value]);

        event(new Login('web', $user, false));

        // ثبت ورود با saveQuietly است، پس هوک saved نباید مجوزها را بازنشانی کند
        $this->assertTrue($user->fresh()->hasPermissionTo(\App\Enums\Permission::ManageStocktakes->value));
    }
}
