<?php

namespace Tests\Feature;

use App\Enums\Permission as Perm;
use App\Filament\Pages\SslSettings;
use App\Models\User;
use App\Services\SslService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * صفحهٔ SSL — دسترسی، نمایش امن وقتی دستیار نصب نیست، و تجزیهٔ وضعیت.
 * (کارهای ریشه‌ای git/nginx/certbot به سیستم‌عامل وابسته‌اند و اینجا Process
 * جعل می‌شود؛ منطقِ برنامه تست می‌شود نه خودِ دستیار.)
 */
class SslSettingsTest extends TestCase
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
        $admin = User::create([
            'name' => 'مدیر', 'email' => 'a@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $admin->syncPermissions(Perm::values());

        return $admin;
    }

    public function test_only_a_settings_manager_can_access(): void
    {
        $this->assertFalse(SslSettings::canAccess()); // مهمان

        $staff = User::create([
            'name' => 'کارشناس', 'email' => 's@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $staff->syncPermissions([Perm::ViewStock->value]);
        $this->actingAs($staff);
        $this->assertFalse(SslSettings::canAccess());

        $this->actingAs($this->admin());
        $this->assertTrue(SslSettings::canAccess());
    }

    public function test_page_renders_install_hint_when_helper_missing(): void
    {
        // روی محیط تست، /usr/local/bin/soorin-ssl وجود ندارد → دستیار نصب نیست.
        Livewire::actingAs($this->admin())
            ->test(SslSettings::class)
            ->assertSuccessful()
            ->assertSee(__('ssl.helper_missing_title'));
    }

    public function test_status_reports_not_installed_without_the_helper(): void
    {
        $status = app(SslService::class)->status();

        $this->assertFalse($status['installed']);
    }

    public function test_status_parses_helper_output(): void
    {
        // اگر دستیار نصب باشد، خروجی key=value درست تجزیه می‌شود.
        // (isHelperInstalled با نبودِ فایل false است؛ اینجا فقط تجزیه را می‌سنجیم.)
        $service = new class extends SslService {
            public function isHelperInstalled(): bool
            {
                return true;
            }
        };

        Process::fake([
            '*' => Process::result(output: "installed=1\nmode=self-signed\nserver_name=192.168.1.36\nforce=on\nexpiry=Jan  1 00:00:00 2035 GMT\n"),
        ]);

        $status = $service->status();

        $this->assertTrue($status['installed']);
        $this->assertSame('self-signed', $status['mode']);
        $this->assertSame('192.168.1.36', $status['server_name']);
        $this->assertSame('on', $status['force']);
    }

    public function test_issue_self_signed_invokes_the_helper_with_validated_args(): void
    {
        $service = new class extends SslService {
            public function isHelperInstalled(): bool
            {
                return true;
            }
        };

        Process::fake([
            '*' => Process::result(output: "installed=1\nmode=self-signed\nserver_name=host.local\nforce=off\n"),
        ]);

        $status = $service->issueSelfSigned('host.local');

        $this->assertSame('self-signed', $status['mode']);

        // آرگومان‌ها به‌صورت آرایه پاس می‌شوند (نه رشته) تا شل تفسیرشان نکند.
        Process::assertRan(function ($process) {
            $cmd = $process->command;

            return is_array($cmd)
                && in_array('self-signed', $cmd, true)
                && in_array('host.local', $cmd, true)
                && $cmd[0] === 'sudo';
        });
    }
}
