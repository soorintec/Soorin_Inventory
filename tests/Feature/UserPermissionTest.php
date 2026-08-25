<?php

namespace Tests\Feature;

use App\Enums\Permission as Perm;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * دسترسی‌های تیک‌خور هر کاربر.
 *
 * نکته اصلی: مجوز مستقیم روی کاربر می‌نشیند، نه از راه نقش — وگرنه برداشتن
 * تیک بی‌اثر بود چون نقش دوباره همان مجوز را می‌داد.
 */
class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::create([
            'name' => 'مدیر', 'email' => 'admin@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $this->actingAs($this->admin);

        Filament::setCurrentPanel('admin');
    }

    /** نقش نباید هیچ مجوزی بدهد، وگرنه برداشتن تیک کار نمی‌کند. */
    public function test_roles_grant_nothing_so_revoking_can_work(): void
    {
        foreach (\Spatie\Permission\Models\Role::all() as $role) {
            $this->assertCount(0, $role->permissions, "نقش {$role->name} نباید مجوز داشته باشد");
        }
    }

    public function test_a_new_admin_gets_every_permission_by_default(): void
    {
        $this->assertTrue($this->admin->can(Perm::ManageStock->value));
        $this->assertTrue($this->admin->can(Perm::RestoreBackups->value));
        $this->assertTrue($this->admin->can(Perm::ManageUsers->value));
    }

    public function test_a_new_staff_gets_the_staff_defaults_only(): void
    {
        $staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);

        $this->assertTrue($staff->can(Perm::ViewStock->value));
        $this->assertTrue($staff->can(Perm::ManageStock->value));
        $this->assertFalse($staff->can(Perm::ManageUsers->value));
        $this->assertFalse($staff->can(Perm::RestoreBackups->value));
    }

    /** قلب خواسته: برداشتن تیک باید واقعاً دسترسی را بگیرد. */
    public function test_revoking_a_permission_actually_removes_access(): void
    {
        $staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);

        $this->assertTrue($staff->can(Perm::ManageStock->value));

        $remaining = array_diff($staff->directPermissionNames(), [Perm::ManageStock->value]);
        $staff->syncPermissions($remaining);

        $this->assertFalse($staff->fresh()->can(Perm::ManageStock->value));
        $this->assertTrue($staff->fresh()->can(Perm::ViewStock->value), 'بقیه دسترسی‌ها باید بمانند');
    }

    /** حتی از یک مدیر هم باید بشود دسترسی گرفت. */
    public function test_a_permission_can_be_revoked_from_an_admin(): void
    {
        $this->admin->syncPermissions([Perm::ViewStock->value]);

        $this->assertTrue($this->admin->fresh()->can(Perm::ViewStock->value));
        $this->assertFalse($this->admin->fresh()->can(Perm::RestoreBackups->value));
    }

    public function test_the_form_saves_the_ticked_permissions(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Users\Pages\CreateUser::class)
            ->fillForm([
                'name'      => 'انباردار محدود',
                'email'     => 'limited@yoursite.com',
                'password'  => 'secret123',
                'user_type' => User::TYPE_STAFF,
                // فرم با پیش‌فرض «کارشناس» باز می‌شود، پس گروه‌های دیگر هم
                // باید صریحاً خالی شوند تا فقط همین یک تیک بماند.
                UserForm::FIELD_PREFIX . 'warehouse'  => [Perm::ViewStock->value],
                UserForm::FIELD_PREFIX . 'purchasing' => [],
                UserForm::FIELD_PREFIX . 'projects'   => [],
                UserForm::FIELD_PREFIX . 'customers'  => [],
                UserForm::FIELD_PREFIX . 'reports'    => [],
                UserForm::FIELD_PREFIX . 'backups'    => [],
                UserForm::FIELD_PREFIX . 'system'     => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'limited@yoursite.com')->firstOrFail();

        $this->assertTrue($created->can(Perm::ViewStock->value));
        $this->assertFalse($created->can(Perm::ManageStock->value), 'تیک نخورده نباید داده شود');
        $this->assertFalse($created->can(Perm::ViewProjects->value));
    }

    /** فرم ساخت باید با پیش‌فرض نوع حساب باز شود، نه خالی. */
    public function test_the_create_form_prefills_the_type_defaults(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Users\Pages\CreateUser::class)
            ->assertFormSet([
                UserForm::FIELD_PREFIX . 'warehouse' => Perm::splitIntoGroups(
                    Perm::defaultsByRole()[User::TYPE_STAFF],
                )['warehouse'],
            ]);
    }

    public function test_editing_a_user_can_take_a_permission_away(): void
    {
        $staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $this->assertTrue($staff->can(Perm::ManageStock->value));

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $staff->getKey()])
            ->fillForm([
                UserForm::FIELD_PREFIX . 'warehouse'  => [Perm::ViewStock->value],
                UserForm::FIELD_PREFIX . 'purchasing' => [],
                UserForm::FIELD_PREFIX . 'projects'   => [],
                UserForm::FIELD_PREFIX . 'customers'  => [],
                UserForm::FIELD_PREFIX . 'reports'    => [],
                UserForm::FIELD_PREFIX . 'backups'    => [],
                UserForm::FIELD_PREFIX . 'system'     => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $staff->refresh();

        $this->assertTrue($staff->can(Perm::ViewStock->value));
        $this->assertFalse($staff->can(Perm::ManageStock->value));
        $this->assertFalse($staff->can(Perm::ViewProjects->value));
    }

    /** فرم ویرایش باید تیک‌های فعلی کاربر را نشان بدهد. */
    public function test_the_edit_form_shows_the_current_permissions(): void
    {
        $staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $staff->syncPermissions([Perm::ViewStock->value, Perm::ViewReports->value]);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $staff->getKey()])
            ->assertFormSet([
                UserForm::FIELD_PREFIX . 'warehouse' => [Perm::ViewStock->value],
                UserForm::FIELD_PREFIX . 'reports'   => [Perm::ViewReports->value],
            ]);
    }

    /** پشتیبان‌گیری به چهار مجوز جدا شکسته شد؛ گرفتن با حذف یکی نیست. */
    public function test_backup_permissions_are_separate(): void
    {
        $user = User::create([
            'name' => 'پشتیبان‌گیر', 'email' => 'b@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $user->syncPermissions([Perm::ViewBackups->value, Perm::CreateBackups->value]);

        $this->assertTrue($user->can(Perm::CreateBackups->value));
        $this->assertFalse($user->can(Perm::DeleteBackups->value));
        $this->assertFalse($user->can(Perm::RestoreBackups->value));
    }

    public function test_a_user_without_backup_view_cannot_open_the_page(): void
    {
        $user = User::create([
            'name' => 'بدون پشتیبان', 'email' => 'nb@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);

        $this->actingAs($user)->get('/admin/backups')->assertForbidden();
        $this->assertFalse(\App\Filament\Pages\Backups::canAccess());
    }

    public function test_a_user_with_only_view_backups_cannot_delete(): void
    {
        $user = User::create([
            'name' => 'فقط بیننده', 'email' => 'v@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $user->syncPermissions([Perm::ViewBackups->value, Perm::CreateBackups->value]);

        $service = app(\App\Services\DatabaseBackupService::class);
        $name = $service->create();

        Livewire::actingAs($user)
            ->test(\App\Filament\Pages\Backups::class)
            ->call('deleteBackup', $name)
            ->assertForbidden();

        $this->assertTrue($service->exists($name));
        $service->delete($name);
    }

    /** بدون دسترسی پروژه، آن بخش‌ها اصلاً نباید دیده شوند. */
    public function test_project_pages_are_hidden_without_the_permission(): void
    {
        $user = User::create([
            'name' => 'انباردار', 'email' => 'w@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $user->syncPermissions([Perm::ViewStock->value, Perm::ViewItems->value]);

        $this->actingAs($user);

        $this->assertFalse(\App\Filament\Resources\Projects\ProjectResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\SystemModels\SystemModelResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\Customers\CustomerResource::canViewAny());
        $this->assertTrue(\App\Filament\Resources\StockBalances\StockBalanceResource::canViewAny());
    }

    protected function tearDown(): void
    {
        foreach (app(\App\Services\DatabaseBackupService::class)->list() as $file) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete('backups/' . $file['name']);
        }

        parent::tearDown();
    }
}
