<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Filament\Pages\Backups;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * پاک‌سازیِ سیاههٔ تغییراتِ قدیمی — نگهداریِ دیتابیس.
 */
class ActivityLogPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'مدیر', 'email' => 'a@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
    }

    /** یک ردیفِ سیاهه با تاریخِ دلخواه می‌سازد. */
    private function logAgedMonths(int $months): ActivityLog
    {
        $log = ActivityLog::create(['action' => 'item_updated']);
        $log->created_at = now()->subMonths($months);
        $log->save();

        return $log;
    }

    public function test_prune_deletes_only_rows_older_than_the_threshold(): void
    {
        $old = $this->logAgedMonths(8);   // قدیمی — باید برود
        $recent = $this->logAgedMonths(2); // تازه — باید بماند

        $deleted = ActivityLog::pruneOlderThan(6);

        $this->assertSame(1, $deleted);
        $this->assertNull(ActivityLog::find($old->id));
        $this->assertNotNull(ActivityLog::find($recent->id));
    }

    public function test_prune_all_empties_the_log(): void
    {
        $this->logAgedMonths(8);
        $this->logAgedMonths(1);
        $this->logAgedMonths(0);

        $deleted = ActivityLog::pruneOlderThan(null);

        $this->assertSame(3, $deleted);
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_the_button_purges_from_the_backups_page(): void
    {
        $this->logAgedMonths(10);
        $this->logAgedMonths(1);

        Livewire::actingAs($this->admin())
            ->test(Backups::class)
            ->assertActionExists('purgeActivity')
            ->callAction('purgeActivity', ['older_than' => '6'])
            ->assertHasNoActionErrors();

        // فقط ردیفِ ۱۰ماهه رفته؛ ردیفِ ۱ماهه مانده.
        $this->assertSame(1, ActivityLog::count());
    }

    public function test_a_user_without_the_permission_does_not_see_the_button(): void
    {
        $staff = User::create([
            'name' => 'کارشناس', 'email' => 's@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        // مجوزِ دیدنِ صفحهٔ بکاپ را دارد ولی مجوزِ پاک‌سازیِ سیاهه را نه.
        $staff->syncPermissions([Permission::ViewBackups->value]);

        Livewire::actingAs($staff)
            ->test(Backups::class)
            ->assertActionHidden('purgeActivity');
    }
}
