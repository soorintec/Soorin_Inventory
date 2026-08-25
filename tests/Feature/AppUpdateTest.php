<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Filament\Pages\AppUpdate;
use App\Support\AppVersion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * صفحهٔ به‌روزرسانی برنامه — نسخهٔ فعلی، دسترسی، و رندر.
 * (خودِ عملیات git/zip چون به شبکه و سیستم‌عامل وابسته است اینجا تست نمی‌شود.)
 */
class AppUpdateTest extends TestCase
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
            'name' => 'مدیر', 'email' => 'a@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
    }

    public function test_the_version_helper_reads_the_version_file(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', AppVersion::current());
    }

    public function test_the_update_page_shows_the_current_version(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AppUpdate::class)
            ->assertSuccessful()
            ->assertSee(AppVersion::current())
            ->assertActionExists('check')
            ->assertActionExists('updateZip');
    }

    public function test_only_a_settings_manager_can_access(): void
    {
        $this->assertTrue(AppUpdate::canAccess() === false); // مهمان

        $staff = User::create([
            'name' => 'کارشناس', 'email' => 's@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $staff->syncPermissions([Permission::ViewStock->value]);
        $this->actingAs($staff);
        $this->assertFalse(AppUpdate::canAccess());

        $this->actingAs($this->admin());
        $this->assertTrue(AppUpdate::canAccess());
    }

    /** نشانِ قرمزِ منو وقتی نسخهٔ جدیدی در کش هست، شمارهٔ نسخه را نشان می‌دهد. */
    public function test_the_nav_badge_shows_the_new_version_when_available(): void
    {
        \Illuminate\Support\Facades\Cache::forever(\App\Services\AppUpdateService::CACHE_KEY, [
            'method' => 'git', 'current' => '1.6.0', 'latest' => '1.7.0',
            'available' => true, 'checked_at' => now()->toIso8601String(),
        ]);

        $this->assertSame('1.7.0', AppUpdate::getNavigationBadge());
        $this->assertSame('danger', AppUpdate::getNavigationBadgeColor());
    }

    /** وقتی برنامه به‌روز است، هیچ نشانی نشان داده نمی‌شود. */
    public function test_the_nav_badge_is_absent_when_up_to_date(): void
    {
        \Illuminate\Support\Facades\Cache::forever(\App\Services\AppUpdateService::CACHE_KEY, [
            'method' => 'git', 'current' => '1.6.0', 'latest' => '1.6.0',
            'available' => false, 'checked_at' => now()->toIso8601String(),
        ]);

        $this->assertNull(AppUpdate::getNavigationBadge());
    }

    /** دکمهٔ «به‌روزرسانی از گیت‌هاب» تا وقتی نسخهٔ جدیدی تأیید نشده دیده نمی‌شود. */
    public function test_the_git_update_button_is_hidden_until_an_update_is_found(): void
    {
        Livewire::actingAs($this->admin())
            ->test(AppUpdate::class)
            ->assertActionHidden('updateGit');
    }

    /** روی مخزنی که همین حالا گیت است، «اتصال به گیت‌هاب» باید خطا بدهد نه اینکه دوباره init کند. */
    public function test_link_to_git_refuses_when_already_a_git_repo(): void
    {
        if (! AppVersion::isGitRepo()) {
            $this->markTestSkipped('محیط تست یک مخزن گیت نیست.');
        }

        $this->expectException(\RuntimeException::class);

        app(\App\Services\AppUpdateService::class)->linkToGit('https://example.test/repo.git');
    }

    /** آدرس نامعتبر باید رد شود. */
    public function test_link_to_git_validates_the_url(): void
    {
        $service = app(\App\Services\AppUpdateService::class);

        // اگر محیط گیت است، همان گارد اول جلو را می‌گیرد؛ در هر دو حالت باید استثنا بدهد.
        $this->expectException(\RuntimeException::class);

        $service->linkToGit('not-a-url');
    }
}
