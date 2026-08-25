<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN]);
        $u->assignRole(User::TYPE_ADMIN);

        return $u;
    }

    private function staff(): User
    {
        $u = User::create(['name' => 'کارشناس', 'email' => 'staff@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_STAFF]);
        $u->assignRole(User::TYPE_STAFF);

        return $u;
    }

    public function test_login_page_is_reachable_and_rtl(): void
    {
        $response = $this->get('/admin/login');
        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
    }

    public function test_admin_can_open_dashboard(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_item_categories_page_loads(): void
    {
        ItemCategory::create(['name' => 'ترک‌بال']);

        $this->actingAs($this->admin())
            ->get('/admin/item-categories')
            ->assertOk()
            ->assertSee('ترک‌بال');
    }

    public function test_warehouses_page_loads(): void
    {
        Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);

        $this->actingAs($this->admin())
            ->get('/admin/warehouses')
            ->assertOk()
            ->assertSee('مرکزی');
    }

    public function test_items_page_loads(): void
    {
        $category = ItemCategory::create(['name' => 'ترک‌بال']);
        Item::create(['item_category_id' => $category->id, 'code' => 'TB-1', 'name' => 'ترک‌بال کوچک']);

        $this->actingAs($this->admin())
            ->get('/admin/items')
            ->assertOk()
            ->assertSee('ترک‌بال کوچک');
    }

    public function test_item_edit_page_with_versions_loads(): void
    {
        $category = ItemCategory::create(['name' => 'ترک‌بال']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'TB-1', 'name' => 'ترک‌بال کوچک']);

        $this->actingAs($this->admin())
            ->get("/admin/items/{$item->id}/edit")
            ->assertOk();
    }

    public function test_stock_balances_page_loads(): void
    {
        $this->actingAs($this->admin())->get('/admin/stock-balances')->assertOk();
    }

    /**
     * ستون «محل استقرار» از دو رابطه تودرتو (balance ← version) می‌آید؛
     * این تست جلوی خرابی بی‌صدای رندر را می‌گیرد.
     */
    public function test_stock_balances_page_shows_the_shelf_address(): void
    {
        $category = ItemCategory::create(['name' => 'کابل و رابط']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'CBL-1', 'name' => 'کابل VGA']);
        $version = $item->versions()->create([
            'version_code' => 'اصلی', 'location' => 'D3/#04', 'notes' => 'یک عدد معیوب',
        ]);
        $warehouse = Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);

        \App\Models\StockBalance::create([
            'item_version_id' => $version->id,
            'warehouse_id'    => $warehouse->id,
            'quantity'        => 16,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/stock-balances')
            ->assertOk()
            ->assertSee('کابل VGA')
            ->assertSee('D3/#04')
            ->assertSee('یک عدد معیوب');   // یادداشت انبار زیر نام کالا
    }

    /**
     * جدول ورژن‌ها یک relation manager است و با درخواست Livewire جدا رندر
     * می‌شود، پس در HTML اولیه صفحه ویرایش نیست و باید مستقیم تست شود.
     */
    public function test_item_versions_table_shows_the_shelf_address(): void
    {
        $category = ItemCategory::create(['name' => 'کابل و رابط']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'CBL-1', 'name' => 'کابل VGA']);
        $version = $item->versions()->create(['version_code' => 'اصلی', 'location' => 'کشوی راست میانی']);

        $this->actingAs($this->admin());

        \Livewire\Livewire::test(
            \App\Filament\Resources\Items\RelationManagers\VersionsRelationManager::class,
            ['ownerRecord' => $item, 'pageClass' => \App\Filament\Resources\Items\Pages\EditItem::class],
        )
            ->assertSuccessful()
            ->assertTableColumnStateSet('location', 'کشوی راست میانی', $version);
    }

    public function test_stock_movements_page_loads(): void
    {
        $this->actingAs($this->admin())->get('/admin/stock-movements')->assertOk();
    }

    public function test_customers_page_loads(): void
    {
        $this->actingAs($this->admin())->get('/admin/customers')->assertOk();
    }

    public function test_staff_has_view_but_not_manage_warehouses(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->get('/admin/warehouses')->assertOk();
        $this->assertFalse($staff->can(\App\Enums\Permission::ManageWarehouses->value));
    }
}
