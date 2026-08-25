<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'مدیر', 'email' => 'admin@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN]);
        $u->assignRole(User::TYPE_ADMIN);

        return $u;
    }

    private function staff(): User
    {
        $u = User::create(['name' => 'کارشناس', 'email' => 'staff@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_STAFF]);
        $u->assignRole(User::TYPE_STAFF);

        return $u;
    }

    public function test_users_page_loads_for_admin(): void
    {
        $this->actingAs($this->admin())->get('/admin/users')->assertOk();
    }

    public function test_staff_cannot_manage_users(): void
    {
        $this->actingAs($this->staff())->get('/admin/users')->assertForbidden();
    }

    public function test_admin_can_create_user_via_resource(): void
    {
        $this->actingAs($this->admin())->get('/admin/users/create')->assertOk();
    }
}
