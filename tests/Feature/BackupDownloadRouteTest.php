<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * دانلود فایل پشتیبان از راه لینک مستقیم (به‌جای اکشن Livewire) — تا روی
 * گوشی هم مطمئن کار کند.
 */
class BackupDownloadRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'مدیر', 'email' => 'a@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
    }

    public function test_an_authorised_user_can_download_an_existing_backup(): void
    {
        $name = app(DatabaseBackupService::class)->create();

        $response = $this->actingAs($this->admin())->get(route('backups.download', ['name' => $name]));

        $response->assertOk();
        $response->assertDownload($name);
    }

    public function test_a_missing_backup_is_404(): void
    {
        $this->actingAs($this->admin())
            ->get(route('backups.download', ['name' => 'backup-nope.sql']))
            ->assertNotFound();
    }

    public function test_a_user_without_backup_permission_is_refused(): void
    {
        $name = app(DatabaseBackupService::class)->create();

        $staff = User::create([
            'name' => 'کارشناس', 'email' => 's@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $staff->syncPermissions([Permission::ViewStock->value]);

        $this->actingAs($staff)
            ->get(route('backups.download', ['name' => $name]))
            ->assertForbidden();
    }
}
