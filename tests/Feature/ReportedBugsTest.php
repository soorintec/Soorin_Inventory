<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * باگ‌هایی که مالک پروژه هنگام کار با پنل گزارش کرد.
 * هر تست پیش از اصلاح نوشته شده تا مطمئن شویم واقعاً همان مشکل را می‌گیرد.
 */
class ReportedBugsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ItemVersion $version;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::create([
            'name' => 'مدیر انبار', 'email' => 'admin@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $this->admin->assignRole(User::TYPE_ADMIN);
        $this->actingAs($this->admin);

        $category = ItemCategory::create(['name' => 'شبکه']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'NET-001', 'name' => 'KVM سوییچ PS2']);
        $this->version = $item->versions()->create(['version_code' => 'اصلی']);
        $this->warehouse = Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);

        app(StockMovementService::class)->recordIn(
            $this->version, $this->warehouse, 5, 0, StockMovement::REASON_INITIAL,
        );
    }

    /**
     * حذف کالایی که تراکنش انبار دارد نباید ۵۰۰ بدهد.
     *
     * کلید خارجی stock_movements عمداً جلوی حذف واقعی را می‌گیرد تا تاریخچه
     * نابود نشود؛ راه درست، حذف نرم است نه شکستن آن قاعده.
     */
    public function test_deleting_an_item_that_has_stock_movements_does_not_explode(): void
    {
        $item = $this->version->item;

        $item->delete();     // نباید QueryException بدهد

        $this->assertSoftDeleted($item);
        $this->assertSame(1, StockMovement::count(), 'تاریخچه تراکنش باید دست‌نخورده بماند');
    }

    /** کالای حذف‌شده نباید در فهرست‌ها دیده شود. */
    public function test_a_deleted_item_disappears_from_listings(): void
    {
        $this->version->item->delete();

        $this->assertSame(0, Item::count());
        $this->assertSame(1, Item::withTrashed()->count());
    }

    /** ورژن‌های کالای حذف‌شده هم باید حذف نرم شوند، نه اینکه آواره بمانند. */
    public function test_deleting_an_item_soft_deletes_its_versions(): void
    {
        $item = $this->version->item;
        $item->delete();

        $this->assertSoftDeleted($this->version);
    }

    public function test_the_stock_movements_page_loads(): void
    {
        $this->get('/admin/stock-movements')->assertOk();
    }

    /**
     * جدول تراکنش‌ها یک کامپوننت Livewire است؛ خطای واقعی هنگام رندر خودِ
     * جدول رخ می‌دهد، نه در HTML اولیه صفحه.
     */
    public function test_the_stock_movements_table_renders_its_rows(): void
    {
        Filament::setCurrentPanel('admin');

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\StockMovements\Pages\ListStockMovements::class)
            ->assertSuccessful()
            ->assertSee('KVM سوییچ PS2');
    }

    /**
     * صفحه تراکنش‌ها نباید دکمه «ایجاد» داشته باشد.
     *
     * باگ گزارش‌شده همین بود: دکمه وجود داشت ولی این Resource فرم ندارد، پس
     * پنجره خالی باز می‌شد و ثبتش با «item_version_id مقدار پیش‌فرض ندارد»
     * می‌ترکید. ساخت دستی سند تراکنش هم قاعده FIFO پروژه را می‌شکند.
     */
    public function test_the_stock_movements_page_has_no_create_button(): void
    {
        Filament::setCurrentPanel('admin');

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\StockMovements\Pages\ListStockMovements::class)
            ->assertSuccessful()
            ->assertActionDoesNotExist('create');

        $this->assertFalse(\App\Filament\Resources\StockMovements\StockMovementResource::canCreate());
    }

    /** صفحه باید توضیح بدهد که این سیاهه چیست و چرا وجود دارد. */
    public function test_the_stock_movements_page_explains_itself(): void
    {
        $this->get('/admin/stock-movements')
            ->assertOk()
            ->assertSee('دفتر تغییرات انبار', false);
    }

    /** فایل پشتیبان باید واقعاً دانلود شود. */
    public function test_a_backup_can_be_downloaded(): void
    {
        Filament::setCurrentPanel('admin');

        $page = Livewire::actingAs($this->admin)->test(\App\Filament\Pages\Backups::class)
            ->callAction(\Filament\Actions\Testing\TestAction::make('create'))
            ->assertHasNoActionErrors();

        $name = app(\App\Services\DatabaseBackupService::class)->list()[0]['name'];

        $page->call('download', $name)->assertFileDownloaded($name);
    }

    /**
     * دکمه‌ها باید در HTML واقعی هم درست دربیایند.
     *
     * تست‌های بالا متد را مستقیم صدا می‌زنند و از کنار قالب رد می‌شوند؛ باگ
     * واقعی همان‌جا بود: «@js» داخل attribute یک کامپوننت Blade کامپایل
     * نمی‌شود و عیناً به مرورگر می‌رسد، پس کلیک هیچ کاری نمی‌کرد.
     */
    public function test_the_backup_buttons_render_a_real_wire_click(): void
    {
        app(\App\Services\DatabaseBackupService::class)->create();

        $html = $this->get('/admin/backups')->assertOk()->getContent();

        $this->assertStringNotContainsString('@js(', $html, 'دستور بلید کامپایل‌نشده به مرورگر رفته است');
        // دانلود حالا لینک مستقیم است (مطمئن‌تر روی گوشی)، نه اکشن Livewire
        $this->assertMatchesRegularExpression('#href="[^"]*/backups/download/backup-[\w.\-]+\.sql"#', $html);
        // حذف همچنان اکشن Livewire است
        $this->assertMatchesRegularExpression('/wire:click="deleteBackup\(\'backup-[\w.\-]+\.sql\'\)"/', $html);
    }

    /** دکمه حذف باید فایل را واقعاً حذف کند. */
    public function test_a_backup_can_be_deleted(): void
    {
        Filament::setCurrentPanel('admin');

        $service = app(\App\Services\DatabaseBackupService::class);

        $page = Livewire::actingAs($this->admin)->test(\App\Filament\Pages\Backups::class)
            ->callAction(\Filament\Actions\Testing\TestAction::make('create'))
            ->assertHasNoActionErrors();

        $name = $service->list()[0]['name'];
        $this->assertTrue($service->exists($name));

        $page->call('deleteBackup', $name);

        $this->assertFalse($service->exists($name));
    }

    protected function tearDown(): void
    {
        foreach (app(\App\Services\DatabaseBackupService::class)->list() as $file) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete('backups/' . $file['name']);
        }

        parent::tearDown();
    }
}
