<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
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
 * صفحه «موجودی انبار» و «مدیریت انبار» پس از بازچینش منو.
 */
class StockPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Item $item;
    private ItemVersion $version;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::create([
            'name' => 'مدیر انبار', 'email' => 'admin@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $this->admin->assignRole(User::TYPE_ADMIN);
        $this->actingAs($this->admin);

        $category = ItemCategory::create(['name' => 'کابل و رابط']);
        $this->item = Item::create([
            'item_category_id' => $category->id, 'code' => 'CBL-001', 'name' => 'کابل VGA',
        ]);
        $this->version = $this->item->versions()->create([
            'version_code' => 'کشوی راست میانی',
            'location'     => 'D3/#04',
            'notes'        => 'یک عدد معیوب',
            'fx_price'     => 12.5,
            'fx_currency'  => 'USD',
        ]);

        $this->warehouse = Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);
        app(StockMovementService::class)->recordIn(
            $this->version, $this->warehouse, 21, 0, StockMovement::REASON_INITIAL,
        );

        Filament::setCurrentPanel('admin');
    }

    private function stockPage(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Items\Pages\ListItems::class);
    }

    public function test_the_stock_page_is_titled_and_explained(): void
    {
        $this->get('/admin/items')
            ->assertOk()
            ->assertSee('موجودی انبار', false);
    }

    /**
     * پنجره «ورژن‌ها» باید ورژن، محل، موجودی، قیمت و یادداشت را نشان بدهد.
     * (پیش از این آبشاری بود؛ حالا برای داشتن سطر عنوان، پنجره‌ای شده است.)
     */
    public function test_the_stock_page_lists_items_with_a_versions_action(): void
    {
        $this->stockPage()
            ->assertSuccessful()
            ->assertSee('کابل VGA')
            ->assertSee('CBL-001')                 // کد کالا در توضیح ستون نام
            ->assertTableActionExists('versions'); // دکمهٔ باز کردن پنجرهٔ ورژن‌ها
    }

    /** محتوای پنجرهٔ ورژن‌ها باید همهٔ جزئیات ورژن را با فاصلهٔ درست نشان دهد. */
    public function test_the_versions_modal_shows_all_the_version_details(): void
    {
        $html = view('filament.tables.item-versions-modal', ['item' => $this->item])->render();

        $this->assertStringContainsString('کشوی راست میانی', $html);  // کد ورژن
        $this->assertStringContainsString('D3/#04', $html);            // محل استقرار
        $this->assertStringContainsString('یک عدد معیوب', $html);      // یادداشت انبار
        $this->assertStringContainsString('۱۲٫۵ دلار', $html);         // قیمت با ارز فارسی
        $this->assertStringContainsString('padding:9px 22px', $html);  // فاصلهٔ ستون‌ها
    }

    public function test_the_stock_page_can_be_filtered_by_category(): void
    {
        $other = ItemCategory::create(['name' => 'نمایشگر']);
        Item::create(['item_category_id' => $other->id, 'code' => 'MON-1', 'name' => 'مانیتور ۲۲ اینچ']);

        $this->stockPage()
            ->filterTable('item_category_id', $this->item->item_category_id)
            ->assertSee('کابل VGA')
            ->assertDontSee('مانیتور ۲۲ اینچ');
    }

    /** فیلتر انبار — «کدام کالاها در این انبار موجودند». */
    public function test_the_stock_page_can_be_filtered_by_warehouse(): void
    {
        $otherWarehouse = Warehouse::create(['name' => 'مرجوعی و معیوب', 'code' => 'DEF']);

        $lonely = Item::create([
            'item_category_id' => $this->item->item_category_id, 'code' => 'CBL-002', 'name' => 'کابل HDMI',
        ]);
        $lonelyVersion = $lonely->versions()->create(['version_code' => 'اصلی']);
        app(StockMovementService::class)->recordIn(
            $lonelyVersion, $otherWarehouse, 3, 0, StockMovement::REASON_INITIAL,
        );

        $this->stockPage()
            ->filterTable('warehouse', $this->warehouse->id)
            ->assertSee('کابل VGA')
            ->assertDontSee('کابل HDMI');
    }

    public function test_the_stock_page_can_show_only_imported_items(): void
    {
        $plain = Item::create([
            'item_category_id' => $this->item->item_category_id, 'code' => 'CBL-003', 'name' => 'کابل ارت کنسول',
        ]);
        $plain->versions()->create(['version_code' => 'اصلی']);

        $this->stockPage()
            ->filterTable('imported', true)
            ->assertSee('کابل VGA')
            ->assertDontSee('کابل ارت کنسول');
    }

    /** باکس «آخرین تغییرات» باید بالای موجودی انبار دیده شود. */
    public function test_the_recent_activity_box_shows_who_changed_what(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Widgets\RecentActivityWidget::class)
            ->assertSuccessful()
            ->assertSee('آخرین تغییرات انبار')
            ->assertSee('مدیر انبار')      // نام کاربر
            ->assertSee('ورود کالا');      // نوع تغییر به فارسی
    }

    /** تغییر نام کالا هم باید در سیاهه بیفتد، نه فقط ورود و خروج. */
    public function test_editing_an_item_is_written_to_the_activity_log(): void
    {
        $before = ActivityLog::where('action', 'item_updated')->count();

        $this->item->update(['name' => 'کابل VGA بلند']);

        $this->assertSame($before + 1, ActivityLog::where('action', 'item_updated')->count());

        $log = ActivityLog::where('action', 'item_updated')->latest('id')->first();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertContains('name', $log->changes['fields']);
        $this->assertSame('ویرایش کالا', $log->actionLabel());
    }

    /** ذخیره بدون تغییر نباید سیاهه را شلوغ کند. */
    public function test_saving_without_a_change_is_not_logged(): void
    {
        $before = ActivityLog::where('action', 'item_updated')->count();

        $this->item->update(['name' => $this->item->name]);

        $this->assertSame($before, ActivityLog::where('action', 'item_updated')->count());
    }

    /** زمان‌ها باید به وقت تهران نمایش داده شوند، نه UTC. */
    public function test_times_are_shown_in_tehran_time(): void
    {
        $utcNoon = \Carbon\Carbon::parse('2026-08-16 12:00:00', 'UTC');

        // تهران +۳:۳۰ نسبت به UTC ← ۱۵:۳۰
        $this->assertStringContainsString('۱۵:۳۰', \App\Support\Jalali::formatDateTime($utcNoon));
    }

    public function test_the_manage_page_offers_stock_in_and_out(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\StockBalances\Pages\ListStockBalances::class)
            ->assertSuccessful()
            ->assertActionExists('recordIn')
            ->assertActionExists('recordOut')
            ->assertActionExists('transfer');
    }

    /**
     * فیلترهای تازهٔ «مدیریت انبار» باید بدون خطای SQL اجرا شوند —
     * به‌ویژه فیلتر کم‌موجودی که ستون بیرونی را با ورژن مقایسه می‌کند.
     */
    public function test_the_manage_page_filters_run_without_error(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\StockBalances\Pages\ListStockBalances::class)
            ->filterTable('in_stock', true)
            ->assertSuccessful()
            ->filterTable('low_stock', true)
            ->assertSuccessful()
            ->filterTable('imported', true)
            ->assertSuccessful();
    }

    /** ورود کالا باید با انتخاب کالا و ورژن کار کند. */
    public function test_stock_in_records_a_movement(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\StockBalances\Pages\ListStockBalances::class)
            ->callAction('recordIn', [
                'item_id'         => $this->item->id,
                'item_version_id' => $this->version->id,
                'warehouse_id'    => $this->warehouse->id,
                'quantity'        => 5,
                'unit_cost'       => 0,
            ])
            ->assertHasNoActionErrors();

        $this->assertEquals(26, $this->version->totalQuantity());
    }

    /** ساخت کالای جدید از «مدیریت انبار» باید ورژن اولش را هم بسازد. */
    public function test_a_new_item_created_here_gets_its_first_version(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\StockBalances\Pages\ListStockBalances::class)
            ->callAction('newItem', [
                'item_category_id' => $this->item->item_category_id,
                'name'             => 'کابل شبکه ۲ متری',
                'code'             => 'CBL-999',
                'unit'             => 'عدد',
                'version_code'     => 'اصلی',
            ])
            ->assertHasNoActionErrors();

        $created = Item::where('code', 'CBL-999')->firstOrFail();
        $this->assertSame(1, $created->versions()->count());
    }

    public function test_a_category_can_be_created_on_the_categories_page(): void
    {
        // ساختِ دسته از خودِ صفحهٔ «دسته‌بندی‌ها» (میان‌بر «دسته‌بندی جدید» در عملیات حذف شد).
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\ItemCategories\Pages\ListItemCategories::class)
            ->callAction('create', ['name' => 'برد الکترونیکی', 'code' => 'PCB', 'spec_template' => []])
            ->assertHasNoActionErrors();

        $this->assertTrue(ItemCategory::where('name', 'برد الکترونیکی')->exists());
    }
}
