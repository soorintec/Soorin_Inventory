<?php

namespace Tests\Feature;

use App\Actions\ReceivePurchase;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\Purchase;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ReceivePurchaseTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private ItemVersion $versionA;
    private ItemVersion $versionB;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create(['name' => 'انباردار', 'email' => 'w@yoursite.com', 'password' => 'secret123']);
        $this->actingAs($user);

        $this->warehouse = Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);

        $category = ItemCategory::create(['name' => 'مانیتور']);
        $itemA = Item::create(['item_category_id' => $category->id, 'code' => 'MON-A', 'name' => 'مانیتور ۲۲']);
        $itemB = Item::create(['item_category_id' => $category->id, 'code' => 'MON-B', 'name' => 'مانیتور ۸']);
        $this->versionA = $itemA->versions()->create(['version_code' => 'A1']);
        $this->versionB = $itemB->versions()->create(['version_code' => 'B1']);
    }

    private function purchase(string $allocation = Purchase::ALLOCATION_VALUE, array $extra = []): Purchase
    {
        return Purchase::create(array_merge([
            'warehouse_id'   => $this->warehouse->id,
            'order_date'     => now(),
            'rate_to_irr'    => 500_000,   // هر واحد ارز = ۵۰۰٬۰۰۰ ریال
            'shipping_cost'  => 1_000_000,
            'customs_cost'   => 500_000,
            'allocation_method' => $allocation,
        ], $extra));
    }

    public function test_purchase_number_is_generated_automatically(): void
    {
        $purchase = $this->purchase();
        $this->assertStringStartsWith('P-', $purchase->number);
    }

    public function test_receiving_creates_lots_with_landed_cost_value_allocation(): void
    {
        $purchase = $this->purchase(Purchase::ALLOCATION_VALUE);

        // ردیف الف: ۵ عدد × ۱۰ ارز = ارزش ۲۵٬۰۰۰٬۰۰۰ ریال (۵۰٪ از کل ارزش ۵۰٬۰۰۰٬۰۰۰)
        $purchase->items()->create(['item_version_id' => $this->versionA->id, 'quantity' => 5, 'fx_unit_price' => 10]);
        // ردیف ب: ۵ عدد × ۱۰ ارز = همان ارزش ۲۵٬۰۰۰٬۰۰۰ ریال (۵۰٪ باقی‌مانده)
        $purchase->items()->create(['item_version_id' => $this->versionB->id, 'quantity' => 5, 'fx_unit_price' => 10]);

        $result = app(ReceivePurchase::class)($purchase);

        $this->assertSame(Purchase::STATUS_RECEIVED, $result->status);
        $this->assertSame(50_000_000, $result->goods_value_irr);   // (5*10*500000)*2
        $this->assertSame(51_500_000, $result->total_cost_irr);    // + 1.5M جانبی

        // هزینه جانبی ۱٬۵۰۰٬۰۰۰ باید مساوی بین دو ردیف هم‌ارزش تقسیم شود: هرکدام ۷۵۰٬۰۰۰ کل → ۱۵۰٬۰۰۰ هر واحد
        $itemA = $purchase->items()->where('item_version_id', $this->versionA->id)->first();
        $this->assertSame(750_000, $itemA->allocated_cost);
        $this->assertSame(5_150_000, $itemA->landed_unit_cost); // (10*500000) + 150000

        $lot = StockLot::where('item_version_id', $this->versionA->id)->first();
        $this->assertSame(5_150_000, $lot->unit_cost);
        $this->assertEquals(5.0, $lot->quantity_remaining);

        $this->assertEquals(5.0, StockBalance::where('item_version_id', $this->versionA->id)->first()->quantity);
    }

    public function test_allocated_cost_sums_exactly_to_peripheral_cost_despite_rounding(): void
    {
        // مقادیر عمداً طوری انتخاب شده‌اند که تقسیم درصدی رند نشود
        $purchase = $this->purchase(Purchase::ALLOCATION_VALUE, ['shipping_cost' => 1_000_003, 'customs_cost' => 0]);
        $purchase->items()->create(['item_version_id' => $this->versionA->id, 'quantity' => 3, 'fx_unit_price' => 7]);
        $purchase->items()->create(['item_version_id' => $this->versionB->id, 'quantity' => 4, 'fx_unit_price' => 11]);

        app(ReceivePurchase::class)($purchase);

        $totalAllocated = (int) $purchase->items()->sum('allocated_cost');
        $this->assertSame(1_000_003, $totalAllocated);
    }

    public function test_weight_based_allocation(): void
    {
        $purchase = $this->purchase(Purchase::ALLOCATION_WEIGHT, ['shipping_cost' => 900_000, 'customs_cost' => 0]);
        // الف: وزن کل ۱۰ کیلو (۱۰ عدد × ۱ کیلو) — ب: وزن کل ۲۰ کیلو (۱۰ عدد × ۲ کیلو) → نسبت ۱:۲
        $purchase->items()->create(['item_version_id' => $this->versionA->id, 'quantity' => 10, 'fx_unit_price' => 5, 'weight_kg' => 1]);
        $purchase->items()->create(['item_version_id' => $this->versionB->id, 'quantity' => 10, 'fx_unit_price' => 5, 'weight_kg' => 2]);

        app(ReceivePurchase::class)($purchase);

        $itemA = $purchase->items()->where('item_version_id', $this->versionA->id)->first();
        $itemB = $purchase->items()->where('item_version_id', $this->versionB->id)->first();

        $this->assertSame(300_000, $itemA->allocated_cost); // ۱/۳ از ۹۰۰٬۰۰۰
        $this->assertSame(600_000, $itemB->allocated_cost); // ۲/۳ از ۹۰۰٬۰۰۰
    }

    public function test_cannot_receive_purchase_twice(): void
    {
        $purchase = $this->purchase();
        $purchase->items()->create(['item_version_id' => $this->versionA->id, 'quantity' => 1, 'fx_unit_price' => 10]);

        app(ReceivePurchase::class)($purchase);

        $this->expectException(RuntimeException::class);
        app(ReceivePurchase::class)($purchase->fresh());
    }

    public function test_cannot_receive_purchase_without_items(): void
    {
        $purchase = $this->purchase();

        $this->expectException(RuntimeException::class);
        app(ReceivePurchase::class)($purchase);
    }
}
