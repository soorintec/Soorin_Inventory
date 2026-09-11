<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_log_in_with_a_username_stored_in_email_column(): void
    {
        $user = User::create([
            'name' => 'علی', 'email' => 'ali', 'password' => 'secret123',
            'user_type' => User::TYPE_ADMIN, 'is_active' => true,
        ]);
        $user->assignRole(User::TYPE_ADMIN);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'ali', 'password' => 'secret123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_real_email_login_still_works(): void
    {
        $user = User::create([
            'name' => 'مدیر', 'email' => 'admin@yoursite.com', 'password' => 'secret123',
            'user_type' => User::TYPE_ADMIN, 'is_active' => true,
        ]);
        $user->assignRole(User::TYPE_ADMIN);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@yoursite.com', 'password' => 'secret123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::create([
            'name' => 'علی', 'email' => 'ali', 'password' => 'secret123',
            'user_type' => User::TYPE_ADMIN, 'is_active' => true,
        ]);
        $user->assignRole(User::TYPE_ADMIN);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'ali', 'password' => 'WRONG'])
            ->call('authenticate')
            ->assertHasFormErrors();

        $this->assertGuest();
    }
}
