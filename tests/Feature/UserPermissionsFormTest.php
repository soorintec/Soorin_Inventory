<?php

namespace Tests\Feature;

use App\Enums\Permission as Perm;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * باگ‌های صفحهٔ دسترسی‌ها: برداشتن یک تیک نباید بقیه را پاک کند یا جابه‌جا کند.
 * فیلدهای تیک، تختِ مستقل‌اند (perm_group_warehouse و…) نه یک مسیر آرایه‌ای مشترک.
 */
class UserPermissionsFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');

        $this->admin = User::create([
            'name' => 'مدیر', 'email' => 'admin@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $this->admin->syncPermissions(Perm::values());

        $this->target = User::create([
            'name' => 'هدف', 'email' => 't@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $this->target->syncPermissions(Perm::values());
    }

    /** فیلدهای تخت فرم را از یک فهرست مجوز می‌سازد. */
    private function fields(array $permissions): array
    {
        $split = Perm::splitIntoGroups($permissions);
        $fields = [];

        foreach (array_keys(Perm::grouped()) as $group) {
            $fields[UserForm::FIELD_PREFIX . $group] = $split[$group] ?? [];
        }

        return $fields;
    }

    public function test_edit_form_hydrates_exact_permissions_not_defaults(): void
    {
        $only = [Perm::ViewStock->value, Perm::ManageStocktakes->value];
        $this->target->syncPermissions($only);

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $this->target->getKey()])
            ->assertFormSet($this->fields($only));
    }

    public function test_updating_one_group_keeps_others(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $this->target->getKey()])
            ->set('data.' . UserForm::FIELD_PREFIX . 'backups', [
                Perm::ViewBackups->value, Perm::CreateBackups->value, Perm::DeleteBackups->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->target->refresh();
        $this->assertFalse($this->target->hasPermissionTo(Perm::RestoreBackups->value));
        $this->assertTrue($this->target->hasPermissionTo(Perm::ManageStocktakes->value));
        $this->assertTrue($this->target->hasPermissionTo(Perm::ManagePurchases->value));
    }

    public function test_unchecking_one_permission_keeps_the_rest(): void
    {
        $kept = array_values(array_diff(Perm::values(), [Perm::RestoreBackups->value]));

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $this->target->getKey()])
            ->fillForm($this->fields($kept))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->target->refresh();
        $this->assertTrue($this->target->hasPermissionTo(Perm::ViewBackups->value));
        $this->assertTrue($this->target->hasPermissionTo(Perm::CreateBackups->value));
        $this->assertTrue($this->target->hasPermissionTo(Perm::DeleteBackups->value));
        $this->assertFalse($this->target->hasPermissionTo(Perm::RestoreBackups->value));
        $this->assertTrue($this->target->hasPermissionTo(Perm::ManageStock->value));
    }

    public function test_permissions_map_to_the_exact_checkbox(): void
    {
        $chosen = [Perm::ViewStock->value, Perm::ManageStocktakes->value];

        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $this->target->getKey()])
            ->fillForm($this->fields($chosen))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing($chosen, $this->target->fresh()->directPermissionNames());
        $this->assertFalse($this->target->fresh()->hasPermissionTo(Perm::ManagePurchases->value));
    }

    public function test_unchecking_everything_removes_all(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $this->target->getKey()])
            ->fillForm($this->fields([]))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $this->target->fresh()->directPermissionNames());
    }
}
