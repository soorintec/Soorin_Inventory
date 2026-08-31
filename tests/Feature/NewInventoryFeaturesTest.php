<?php

namespace Tests\Feature;

use App\Filament\Resources\ItemCategories\Pages\ListItemCategories;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewInventoryFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');

        $this->admin = User::create(['name' => 'مدیر', 'email' => 'admin@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN]);
        $this->admin->assignRole(User::TYPE_ADMIN);
        $this->warehouse = Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);
    }

    private function item(string $code, string $name): Item
    {
        $cat = ItemCategory::firstOrCreate(['name' => 'دستهٔ تست']);

        return Item::create(['item_category_id' => $cat->id, 'code' => $code, 'name' => $name]);
    }

    /** آدرسِ عکسِ کالا از دیسکِ item-images ساخته می‌شود. */
    public function test_item_image_url(): void
    {
        $item = $this->item('IMG-1', 'کالای عکس‌دار');
        $this->assertNull($item->imageUrl());

        $item->update(['image' => 'photo.jpg']);
        $this->assertStringContainsString('item-images', $item->fresh()->imageUrl());
        $this->assertStringContainsString('photo.jpg', $item->fresh()->imageUrl());
    }

    /** گزارشِ نیازمندِ سفارش: کالای زیرِ حد هشدار می‌آید، کالای بالای حد نمی‌آید. */
    public function test_reorder_report_lists_only_items_below_min(): void
    {
        // زیر حد: min=10، موجودی=3
        $low = $this->item('LOW-1', 'کالای کم‌موجود');
        $lowV = $low->versions()->create(['version_code' => 'اصلی', 'min_stock' => 10]);
        StockBalance::create(['item_version_id' => $lowV->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 3]);

        // بالای حد: min=5، موجودی=8
        $ok = $this->item('OK-1', 'کالای پرموجود');
        $okV = $ok->versions()->create(['version_code' => 'اصلی', 'min_stock' => 5]);
        StockBalance::create(['item_version_id' => $okV->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 8]);

        $response = $this->actingAs($this->admin)->get(route('warehouse.print.reorder'));

        $response->assertOk();
        $response->assertSee('کالای کم‌موجود');
        $response->assertDontSee('کالای پرموجود');
    }

    /** فیلترِ «وضعیت موجودی = تمام‌شده» در فهرست کالاها فقط کالای صفر را نشان می‌دهد. */
    public function test_out_of_stock_filter_shows_only_empty_items(): void
    {
        $out = $this->item('OUT-1', 'تمام‌شده');
        $out->versions()->create(['version_code' => 'اصلی']); // بدون موجودی

        $full = $this->item('FULL-1', 'موجود');
        $fullV = $full->versions()->create(['version_code' => 'اصلی']);
        StockBalance::create(['item_version_id' => $fullV->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 12]);

        Livewire::actingAs($this->admin)
            ->test(ListItems::class)
            ->filterTable('stock_state', 'out')
            ->assertCanSeeTableRecords([$out])
            ->assertCanNotSeeTableRecords([$full]);
    }

    /** چاپِ کاردکس: مسیر باز می‌شود و حرکت‌های کالا را نشان می‌دهد. */
    public function test_kardex_print_route_renders(): void
    {
        $item = $this->item('K-1', 'کالای کاردکس');
        $v = $item->versions()->create(['version_code' => 'اصلی']);
        StockMovement::create([
            'item_version_id' => $v->id, 'warehouse_id' => $this->warehouse->id,
            'direction' => StockMovement::DIRECTION_IN, 'reason' => StockMovement::REASON_PURCHASE,
            'quantity' => 7, 'unit_cost' => 0, 'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('warehouse.print.kardex', $item));

        $response->assertOk();
        $response->assertSee('کالای کاردکس');
        $response->assertSee(__('stock.reasons.purchase'));
    }

    /** دسته‌ای که کالا دارد نباید حذف شود (وگرنه خطای کلید خارجی). */
    public function test_a_category_with_items_cannot_be_deleted(): void
    {
        $cat = ItemCategory::create(['name' => 'دستهٔ پرکالا']);
        Item::create(['item_category_id' => $cat->id, 'code' => 'C-1', 'name' => 'کالا']);

        Livewire::actingAs($this->admin)
            ->test(ListItemCategories::class)
            ->callTableAction('delete', $cat);

        $this->assertDatabaseHas('item_categories', ['id' => $cat->id]);
    }
}
