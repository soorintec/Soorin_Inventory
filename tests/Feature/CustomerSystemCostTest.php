<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSystem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تست قیمت تمام‌شده سامانه اجراشده — قطعات از انبار FIFO خارج و قیمتشان
 * منجمد می‌شود؛ تغییر قیمت بعدی کالا نباید سوابق را عوض کند.
 */
class CustomerSystemCostTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private ItemVersion $version;
    private CustomerSystem $system;
    private StockMovementService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $user = \App\Models\User::create(['name' => 'انباردار', 'email' => 'w@yoursite.com', 'password' => 'secret123']);
        $this->actingAs($user);

        $this->warehouse = Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);
        $category = ItemCategory::create(['name' => 'مانیتور']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'MON', 'name' => 'مانیتور ۲۲']);
        $this->version = $item->versions()->create(['version_code' => 'A1']);

        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $this->system = CustomerSystem::create(['code' => 'CS-1', 'customer_id' => $customer->id, 'name' => 'سالن کنترل']);
        $this->stock = app(StockMovementService::class);
    }

    /** شبیه‌سازی همان کاری که PartsRelationManager هنگام افزودن قطعه می‌کند. */
    private function installPart(float $qty): void
    {
        $movements = $this->stock->recordOut($this->version, $this->warehouse, $qty, StockMovement::REASON_PROJECT);
        $totalCost = array_sum(array_map(fn ($m) => (float) $m->quantity * $m->unit_cost, $movements));

        $this->system->parts()->create([
            'item_version_id' => $this->version->id,
            'quantity'        => $qty,
            'unit_cost'       => (int) round($totalCost / $qty),
            'installed_at'    => now(),
        ]);
        $this->system->recalculateTotalCost();
    }

    public function test_installing_part_reduces_stock_and_freezes_cost(): void
    {
        $this->stock->recordIn($this->version, $this->warehouse, 10, 3_000_000, StockMovement::REASON_INITIAL);

        $this->installPart(2);

        $this->assertEquals(8.0, StockBalance::first()->quantity);
        $part = $this->system->parts()->first();
        $this->assertSame(3_000_000, $part->unit_cost);
        $this->assertSame(6_000_000, $this->system->fresh()->total_cost);
    }

    public function test_part_cost_uses_fifo_weighted_average_across_lots(): void
    {
        // لات قدیمی ۳ عدد × ۲٬۰۰۰٬۰۰۰، لات جدید ۵ عدد × ۴٬۰۰۰٬۰۰۰
        $this->stock->recordIn($this->version, $this->warehouse, 3, 2_000_000, StockMovement::REASON_INITIAL, receivedAt: now()->subDays(5));
        $this->stock->recordIn($this->version, $this->warehouse, 5, 4_000_000, StockMovement::REASON_PURCHASE, receivedAt: now());

        // مصرف ۴ عدد: ۳ از لات قدیمی + ۱ از لات جدید = (3×2M + 1×4M)/4 = 2.5M
        $this->installPart(4);

        $part = $this->system->parts()->first();
        $this->assertSame(2_500_000, $part->unit_cost);
        $this->assertSame(10_000_000, $this->system->fresh()->total_cost);
    }

    public function test_later_price_change_does_not_affect_frozen_part_cost(): void
    {
        $this->stock->recordIn($this->version, $this->warehouse, 5, 3_000_000, StockMovement::REASON_INITIAL);
        $this->installPart(1);

        // خرید جدید با قیمت متفاوت
        $this->stock->recordIn($this->version, $this->warehouse, 5, 9_000_000, StockMovement::REASON_PURCHASE);

        // قیمت قطعه نصب‌شده قبلی نباید عوض شود
        $this->assertSame(3_000_000, $this->system->parts()->first()->unit_cost);
    }
}
