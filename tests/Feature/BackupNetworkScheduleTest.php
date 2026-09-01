<?php

namespace Tests\Feature;

use App\Filament\Pages\Backups;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\NetworkBackupService;
use App\Support\BackupSettings;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * بکاپ روی پوشهٔ شبکه + زمان‌بندیِ خودکار + مجوزِ اختصاصی.
 *
 * مقصدِ شبکه در این تست‌ها یک پوشهٔ موقتِ محلی است (همان مسیری که یک اشتراکِ
 * mount‌شده هم می‌شود)؛ حالتِ SMB به smbclient نیاز دارد و اینجا آزموده نمی‌شود.
 */
class BackupNetworkScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $netDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');

        $this->admin = User::create([
            'name' => 'مدیر', 'email' => 'admin@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $this->admin->assignRole(User::TYPE_ADMIN);

        // یک قلم داده تا فایلِ پشتیبان خالی نباشد.
        $cat = ItemCategory::create(['name' => 'کابل']);
        Item::create(['item_category_id' => $cat->id, 'code' => 'C-1', 'name' => 'کابل VGA']);

        $this->netDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'soorin-net-' . uniqid();

        foreach (app(DatabaseBackupService::class)->list() as $old) {
            app(DatabaseBackupService::class)->delete($old['name']);
        }
    }

    // ---------------------------------------------------------- BackupSettings

    public function test_settings_are_saved_and_password_is_encrypted(): void
    {
        BackupSettings::save([
            'network_enabled'  => true,
            'network_path'     => '//srv/backups',
            'network_username' => 'winuser',
            'network_password' => 'winpass',
            'schedule_enabled' => true,
            'schedule_frequency' => 'weekly',
            'schedule_time'    => '03:30',
            'schedule_weekday' => 3,
            'schedule_monthday' => 1,
        ]);

        $this->assertTrue(BackupSettings::networkEnabled());
        $this->assertSame('//srv/backups', BackupSettings::networkPath());
        $this->assertSame('winuser', BackupSettings::networkUsername());
        $this->assertSame('winpass', BackupSettings::networkPassword());
        $this->assertSame('weekly', BackupSettings::frequency());
        $this->assertSame('03:30', BackupSettings::time());
        $this->assertSame(3, BackupSettings::weekday());

        // رمز نباید به‌صورتِ خام در دیتابیس بنشیند.
        $raw = Setting::where('key', 'backup.network_password')->value('value');
        $this->assertNotSame('winpass', $raw);
    }

    public function test_empty_password_keeps_the_previous_one(): void
    {
        BackupSettings::save(['network_password' => 'first']);
        BackupSettings::save(['network_password' => '']); // خالی → دست‌نخورده

        $this->assertSame('first', BackupSettings::networkPassword());
    }

    // ----------------------------------------------------- NetworkBackupService

    public function test_test_and_push_work_on_a_local_folder(): void
    {
        $service = app(NetworkBackupService::class);

        // پوشه هنوز وجود ندارد؛ سرویس باید بسازدش و بگوید قابلِ نوشتن است.
        $result = $service->test($this->netDir);
        $this->assertTrue($result['ok'], $result['message']);
        $this->assertDirectoryExists($this->netDir);

        // یک فایلِ نمونه بساز و push کن.
        $local = tempnam(sys_get_temp_dir(), 'soorin-src');
        file_put_contents($local, 'hello');

        $push = $service->pushToPath($this->netDir, $local, 'copy.sql');
        $this->assertTrue($push['ok'], $push['message']);
        $this->assertFileExists($this->netDir . DIRECTORY_SEPARATOR . 'copy.sql');

        @unlink($local);
    }

    public function test_an_empty_path_is_reported(): void
    {
        $result = app(NetworkBackupService::class)->test('');
        $this->assertFalse($result['ok']);
    }

    // ------------------------------------------------------- زمان‌بندیِ خودکار

    public function test_schedule_is_not_due_when_disabled(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        // schedule_enabled خاموش است
        $this->artisan('soorin:scheduled-backup')->assertSuccessful();

        $this->assertCount(0, app(DatabaseBackupService::class)->list());
        Carbon::setTestNow();
    }

    public function test_scheduled_backup_runs_when_due_and_pushes_to_network(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00'); // بعد از ساعتِ پیش‌فرضِ 02:00

        BackupSettings::save([
            'network_enabled'   => true,
            'network_path'      => $this->netDir,
            'schedule_enabled'  => true,
            'schedule_frequency' => 'daily',
            'schedule_time'     => '02:00',
        ]);

        $this->artisan('soorin:scheduled-backup')->assertSuccessful();

        // یک بکاپِ محلی ساخته شد و روی پوشهٔ شبکه هم ریخته شد.
        $local = app(DatabaseBackupService::class)->list();
        $this->assertCount(1, $local);
        $this->assertFileExists($this->netDir . DIRECTORY_SEPARATOR . $local[0]['name']);
        $this->assertNotNull(BackupSettings::lastRun());

        // اجرای دوباره در همین دوره نباید بکاپِ تازه بسازد.
        $this->artisan('soorin:scheduled-backup')->assertSuccessful();
        $this->assertCount(1, app(DatabaseBackupService::class)->list());

        Carbon::setTestNow();
    }

    public function test_weekly_schedule_skips_the_wrong_weekday(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00'); // سه‌شنبه (dayOfWeek=2)

        BackupSettings::save([
            'schedule_enabled'  => true,
            'schedule_frequency' => 'weekly',
            'schedule_time'     => '02:00',
            'schedule_weekday'  => (now()->dayOfWeek + 1) % 7, // روزی جز امروز
        ]);

        $command = app(\App\Console\Commands\ScheduledBackupCommand::class);
        $this->assertFalse($command->isDue(now()));

        Carbon::setTestNow();
    }

    /**
     * زمان‌بندی باید به وقتِ تهران سنجیده شود، نه UTC.
     *
     * ۰۲:۰۰ به وقتِ UTC یعنی ۰۵:۳۰ به وقتِ تهران. با ساعتِ مقررِ ۰۵:۰۰،
     * به وقتِ تهران رسیده‌ایم (باید بگیرد) ولی به وقتِ UTC هنوز نه. اگر
     * دستور اشتباهاً UTC را مبنا بگیرد، این تست می‌افتد.
     */
    public function test_schedule_is_measured_in_tehran_time_not_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 02:00:00', 'UTC'));

        BackupSettings::save([
            'schedule_enabled'  => true,
            'schedule_frequency' => 'daily',
            'schedule_time'     => '05:00',
        ]);

        $command = app(\App\Console\Commands\ScheduledBackupCommand::class);
        $this->assertTrue($command->isDue(now()), 'باید به وقتِ تهران «رسیده» باشد.');

        Carbon::setTestNow();
    }

    public function test_force_option_backs_up_regardless_of_schedule(): void
    {
        $this->artisan('soorin:scheduled-backup', ['--force' => true])->assertSuccessful();
        $this->assertCount(1, app(DatabaseBackupService::class)->list());
    }

    /** اجرای زمان‌بند (schedule:run) باید ضربان را بنویسد — همان چیزی که تشخیصِ صفحه به آن تکیه دارد. */
    public function test_running_the_scheduler_writes_a_heartbeat(): void
    {
        $this->assertNull(BackupSettings::schedulerHeartbeat());

        $this->artisan('schedule:run');

        $this->assertNotNull(BackupSettings::schedulerHeartbeat());
        $this->assertTrue(BackupSettings::isSchedulerAlive());
    }

    /** تشخیصِ سلامتِ زمان‌بند: ضربانِ تازه = زنده، ضربانِ کهنه یا نبود = خاموش. */
    public function test_scheduler_alive_reflects_heartbeat_freshness(): void
    {
        $this->assertFalse(BackupSettings::isSchedulerAlive()); // هنوز ضربانی نیست

        Setting::set('backup.scheduler_heartbeat', now()->toIso8601String(), 'backup', 'string');
        $this->assertTrue(BackupSettings::isSchedulerAlive());

        Setting::set('backup.scheduler_heartbeat', now()->subMinutes(10)->toIso8601String(), 'backup', 'string');
        $this->assertFalse(BackupSettings::isSchedulerAlive());
    }

    // ----------------------------------------------------------------- مجوز/UI

    public function test_only_a_user_with_the_settings_permission_sees_the_settings_action(): void
    {
        // کاربری که فقط «دیدن پشتیبان» دارد، نه تنظیمات.
        $viewer = User::create([
            'name' => 'بیننده', 'email' => 'v@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $viewer->givePermissionTo(\App\Enums\Permission::ViewBackups->value);

        Livewire::actingAs($viewer)->test(Backups::class)
            ->assertActionHidden('backupSettings');

        Livewire::actingAs($this->admin)->test(Backups::class)
            ->assertActionVisible('backupSettings');
    }

    public function test_settings_action_persists_the_form(): void
    {
        Livewire::actingAs($this->admin)->test(Backups::class)
            ->callAction('backupSettings', [
                'network_enabled'  => true,
                'network_path'     => $this->netDir,
                'network_username' => 'u',
                'network_password' => 'p',
                'schedule_enabled' => true,
                'schedule_frequency' => 'daily',
                'schedule_time'    => '01:15',
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue(BackupSettings::networkEnabled());
        $this->assertSame($this->netDir, BackupSettings::networkPath());
        $this->assertSame('01:15', BackupSettings::time());
    }

    protected function tearDown(): void
    {
        foreach (app(DatabaseBackupService::class)->list() as $file) {
            Storage::disk('local')->delete('backups/' . $file['name']);
        }

        if (is_dir($this->netDir)) {
            foreach (glob($this->netDir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->netDir);
        }

        Carbon::setTestNow();

        parent::tearDown();
    }
}
