<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * کاربری که از پنل ساخته می‌شود باید نقش متناظر نوع حسابش را بگیرد،
 * وگرنه بعد از ورود پنل کاملاً خالی می‌بیند (هیچ مجوزی ندارد).
 */
class UserRoleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_new_staff_receives_role_and_permissions(): void
    {
        $user = User::create([
            'name' => 'کارشناس تازه', 'email' => 'new@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);

        $this->assertTrue($user->hasRole(User::TYPE_STAFF));
        $this->assertTrue($user->can(Permission::ViewItems->value));
        $this->assertFalse($user->can(Permission::ManageUsers->value));
    }

    public function test_new_admin_gets_full_permissions(): void
    {
        $user = User::create([
            'name' => 'مدیر تازه', 'email' => 'admin2@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);

        $this->assertTrue($user->can(Permission::ManageUsers->value));
        $this->assertTrue($user->can(Permission::ManageWarehouses->value));
    }

    public function test_changing_user_type_updates_the_role(): void
    {
        $user = User::create([
            'name' => 'کاربر', 'email' => 'u@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $this->assertFalse($user->can(Permission::ManageUsers->value));

        $user->update(['user_type' => User::TYPE_ADMIN]);

        $user->refresh();
        $this->assertTrue($user->hasRole(User::TYPE_ADMIN));
        $this->assertFalse($user->hasRole(User::TYPE_STAFF));
        $this->assertTrue($user->can(Permission::ManageUsers->value));
    }

    public function test_new_staff_can_actually_see_panel_resources(): void
    {
        $user = User::create([
            'name' => 'کارشناس', 'email' => 's@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);

        $this->actingAs($user)->get('/admin/items')->assertOk();
    }
}
