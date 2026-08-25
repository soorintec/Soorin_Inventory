<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * دستور راه‌اندازی اولیه روی هاست تازه: مجوزها، انبار پیش‌فرض و کاربر مدیر.
 */
class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_up_permissions_warehouse_and_an_admin(): void
    {
        $this->artisan('soorin:install', [
            '--name' => 'مدیر تست',
            '--email' => 'boss@example.com',
            '--password' => 'secret1234',
        ])->assertSuccessful();

        // انبار پیش‌فرض
        $this->assertDatabaseHas('warehouses', ['code' => 'MAIN']);
        $this->assertDatabaseHas('warehouses', ['code' => 'DEF']);

        // کاربر مدیر با دسترسی کامل
        $admin = User::where('email', 'boss@example.com')->firstOrFail();
        $this->assertSame(User::TYPE_ADMIN, $admin->user_type);
        $this->assertTrue($admin->can(Permission::ManageStock->value));
        $this->assertTrue($admin->can(Permission::RestoreBackups->value));
    }

    public function test_running_it_twice_updates_the_password_and_does_not_duplicate(): void
    {
        $this->artisan('soorin:install', [
            '--name' => 'مدیر', '--email' => 'boss@example.com', '--password' => 'first-pass-1',
        ])->assertSuccessful();

        $this->artisan('soorin:install', [
            '--name' => 'مدیر', '--email' => 'boss@example.com', '--password' => 'second-pass-2',
        ])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'boss@example.com')->count());
        $this->assertSame(2, Warehouse::count());   // انبارها تکراری نشدند

        // گذرواژه به‌روز شده است
        $this->assertTrue(auth()->validate(['email' => 'boss@example.com', 'password' => 'second-pass-2']));
    }

    public function test_a_bad_email_does_not_create_an_admin(): void
    {
        $this->artisan('soorin:install', [
            '--name' => 'مدیر', '--email' => 'not-an-email', '--password' => 'secret1234',
        ])->assertSuccessful();

        $this->assertSame(0, User::where('user_type', User::TYPE_ADMIN)->count());
    }
}
