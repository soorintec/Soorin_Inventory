<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * پشتیبان‌گیری و بازیابی. مهم‌ترین تست، رفت‌وبرگشت کامل است: داده‌ای که
 * پشتیبان گرفته می‌شود باید بعد از حذف و بازیابی، دقیقاً همان باشد.
 */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseBackupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DatabaseBackupService::class);

        foreach ($this->service->list() as $old) {
            $this->service->delete($old['name']);
        }
    }

    private function makeItems(): ItemCategory
    {
        $category = ItemCategory::create(['name' => 'کابل و رابط']);

        Item::create(['item_category_id' => $category->id, 'code' => 'CBL-1', 'name' => 'کابل VGA']);
        Item::create([
            'item_category_id' => $category->id, 'code' => 'CBL-2',
            'name' => 'کابل شبکه خام', 'unit' => 'متر',
            // نقل‌قول و نقطه‌ویرگول عمداً داخل داده‌اند: اگر تقسیم دستورها
            // ساده‌انگارانه باشد، همین‌جا می‌شکند
            'description' => "توضیح با ' نقل‌قول و ; نقطه‌ویرگول و \"دابل کوت\"",
        ]);

        return $category;
    }

    public function test_a_backup_file_is_created(): void
    {
        $this->makeItems();

        $name = $this->service->create();

        $this->assertTrue($this->service->exists($name));
        $this->assertStringContainsString('INSERT INTO `items`', file_get_contents($this->service->absolutePath($name)));
    }

    /** رفت‌وبرگشت کامل — داده حذف‌شده باید برگردد. */
    public function test_restore_brings_deleted_data_back(): void
    {
        $this->makeItems();
        $backup = $this->service->create();

        Item::query()->delete();
        ItemCategory::query()->delete();
        $this->assertSame(0, Item::count());

        $this->service->restore($this->service->absolutePath($backup));

        $this->assertSame(2, Item::count());
        $this->assertSame('کابل VGA', Item::where('code', 'CBL-1')->value('name'));
    }

    /** داده‌ای که خودش «؛» و نقل‌قول دارد نباید موقع بازیابی بشکند. */
    public function test_data_containing_quotes_and_semicolons_survives(): void
    {
        $this->makeItems();
        $original = Item::where('code', 'CBL-2')->value('description');

        $backup = $this->service->create();
        Item::query()->delete();
        $this->service->restore($this->service->absolutePath($backup));

        $this->assertSame($original, Item::where('code', 'CBL-2')->value('description'));
    }

    public function test_restore_takes_a_safety_backup_first(): void
    {
        $this->makeItems();
        $backup = $this->service->create();

        $before = count($this->service->list());
        $safety = $this->service->restore($this->service->absolutePath($backup));

        $this->assertTrue($this->service->exists($safety));
        $this->assertCount($before + 1, $this->service->list());
    }

    public function test_the_newest_backup_is_listed_first(): void
    {
        $this->makeItems();

        $first = $this->service->create();
        sleep(1);                       // مرتب‌سازی بر اساس زمان فایل است
        $second = $this->service->create();

        $names = array_column($this->service->list(), 'name');

        $this->assertSame($second, $names[0]);
        $this->assertContains($first, $names);
    }

    /**
     * دو پشتیبان در یک ثانیه نباید هم‌نام شوند.
     *
     * قبلاً نام فقط تا ثانیه دقت داشت و پشتیبانِ ایمنیِ بازیابی، دقیقاً روی
     * همان فایلی می‌نشست که داشتیم از آن بازیابی می‌کردیم — یعنی مبدأ را
     * پیش از خواندن نابود می‌کرد.
     */
    public function test_two_backups_in_the_same_second_do_not_collide(): void
    {
        $this->makeItems();

        $first = $this->service->create();
        $second = $this->service->create();

        $this->assertNotSame($first, $second);
        $this->assertTrue($this->service->exists($first));
        $this->assertTrue($this->service->exists($second));
    }

    public function test_a_path_traversal_filename_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->absolutePath('../../.env');
    }

    public function test_a_non_sql_filename_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->absolutePath('backup.php');
    }

    public function test_only_a_user_with_the_permission_sees_the_page(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_ADMIN);

        $staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $staff->assignRole(User::TYPE_STAFF);

        $this->actingAs($admin)->get('/admin/backups')->assertOk();

        // فیلامنت برای Page (برخلاف Resource) به‌جای ۴۰۳ کاربر بی‌مجوز را
        // ریدایرکت می‌کند؛ مهم این است که صفحه باز نشود و در منو هم نیاید.
        $this->actingAs($staff)->get('/admin/backups')->assertRedirect();

        $this->actingAs($staff);
        $this->assertFalse(\App\Filament\Pages\Backups::canAccess());
        $this->assertFalse(\App\Filament\Pages\Backups::shouldRegisterNavigation());

        $this->actingAs($admin);
        $this->assertTrue(\App\Filament\Pages\Backups::canAccess());
    }

    protected function tearDown(): void
    {
        foreach ($this->service->list() as $file) {
            Storage::disk('local')->delete('backups/' . $file['name']);
        }

        parent::tearDown();
    }
}
