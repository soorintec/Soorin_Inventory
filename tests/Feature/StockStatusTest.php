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
 * ستون وضعیت موجودی: دایره سبز/زرد/قرمز و مرتب‌سازی روی آن.
 */
class StockStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private Item $green;
    private Item $yellow;
    private Item $red;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        $category = ItemCategory::create(['name' => 'کابل و رابط']);
        $this->warehouse = Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);
        $stock = app(StockMovementService::class);

        // سبز: موجودی ۲۰، حد هشدار ۵
        $this->green = Item::create(['item_category_id' => $category->id, 'code' => 'G-1', 'name' => 'کالای سبز']);
        $greenVersion = $this->green->versions()->create(['version_code' => 'اصلی', 'min_stock' => 5]);
        $stock->recordIn($greenVersion, $this->warehouse, 20, 0, StockMovement::REASON_INITIAL);

        // زرد: موجودی ۵، حد هشدار ۵ (روی حد)
        $this->yellow = Item::create(['item_category_id' => $category->id, 'code' => 'Y-1', 'name' => 'کالای زرد']);
        $yellowVersion = $this->yellow->versions()->create(['version_code' => 'اصلی', 'min_stock' => 5]);
        $stock->recordIn($yellowVersion, $this->warehouse, 5, 0, StockMovement::REASON_INITIAL);

        // قرمز: موجودی صفر
        $this->red = Item::create(['item_category_id' => $category->id, 'code' => 'R-1', 'name' => 'کالای قرمز']);
        $this->red->versions()->create(['version_code' => 'اصلی', 'min_stock' => 5]);
    }

    public function test_stock_above_the_threshold_is_green(): void
    {
        $this->assertSame(Item::STATUS_OK, $this->green->stockStatus());
    }

    /** «روی حد هشدار» هم زرد است، نه سبز — خواسته صریح مالک پروژه. */
    public function test_stock_exactly_on_the_threshold_is_yellow(): void
    {
        $this->assertSame(Item::STATUS_LOW, $this->yellow->stockStatus());
    }

    public function test_stock_below_the_threshold_is_yellow(): void
    {
        app(StockMovementService::class)->recordOut(
            $this->yellow->versions->first(), $this->warehouse, 2, StockMovement::REASON_PROJECT,
        );

        $this->assertSame(Item::STATUS_LOW, $this->yellow->fresh()->stockStatus());
    }

    public function test_zero_stock_is_red(): void
    {
        $this->assertSame(Item::STATUS_OUT, $this->red->stockStatus());
    }

    /** کالای بدون حد هشدار تا وقتی موجودی دارد سبز است. */
    public function test_an_item_without_a_threshold_is_green_while_it_has_stock(): void
    {
        $item = Item::create([
            'item_category_id' => $this->green->item_category_id, 'code' => 'N-1', 'name' => 'بدون حد',
        ]);
        $version = $item->versions()->create(['version_code' => 'اصلی']);
        app(StockMovementService::class)->recordIn($version, $this->warehouse, 1, 0, StockMovement::REASON_INITIAL);

        $this->assertSame(Item::STATUS_OK, $item->fresh()->stockStatus());
    }

    /** حد هشدار کالا = سخت‌گیرانه‌ترین حد ورژن‌هایش. */
    public function test_the_item_threshold_is_the_highest_of_its_versions(): void
    {
        $this->green->versions()->create(['version_code' => 'دوم', 'min_stock' => 50]);

        $this->assertEquals(50, $this->green->fresh()->minStock());
        $this->assertSame(Item::STATUS_LOW, $this->green->fresh()->stockStatus());
    }

    public function test_the_status_column_is_shown(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Items\Pages\ListItems::class)
            ->assertSuccessful()
            ->assertTableColumnExists('stock_status');
    }

    /** مرتب‌سازی صعودی باید قرمزها را بالای بالا بیاورد، بعد زرد، بعد سبز. */
    public function test_sorting_by_status_puts_out_of_stock_first(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Items\Pages\ListItems::class)
            ->sortTable('stock_status')
            ->assertCanSeeTableRecords([$this->red, $this->yellow, $this->green], inOrder: true);
    }

    public function test_sorting_the_other_way_puts_healthy_stock_first(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Items\Pages\ListItems::class)
            ->sortTable('stock_status', 'desc')
            ->assertCanSeeTableRecords([$this->green, $this->yellow, $this->red], inOrder: true);
    }

    public function test_the_low_stock_filter_hides_healthy_items(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Resources\Items\Pages\ListItems::class)
            ->filterTable('low_stock', true)
            ->assertCanSeeTableRecords([$this->red, $this->yellow])
            ->assertCanNotSeeTableRecords([$this->green]);
    }
}
