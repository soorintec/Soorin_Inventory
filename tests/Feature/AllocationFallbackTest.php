<?php

namespace Tests\Feature;

use App\Actions\ReceivePurchase;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * وقتی مبنای سرشکن انتخاب‌شده صفر است (مثلاً روش وزنی ولی وزن وارد نشده)،
 * نباید کل هزینه روی ردیف آخر بیفتد — که قیمت تمام‌شده را بی‌سروصدا
 * خراب می‌کرد. باید به مبنای معنادار بعدی برگردد.
 */
class AllocationFallbackTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $wh;
    private ItemVersion $vA;
    private ItemVersion $vB;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::create(['name' => 'انباردار', 'email' => 'w@dpst.ir', 'password' => 'secret123']);
        $this->actingAs($user);

        $this->wh = Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);
        $cat = ItemCategory::create(['name' => 'تست']);
        $a = Item::create(['item_category_id' => $cat->id, 'code' => 'A', 'name' => 'الف']);
        $b = Item::create(['item_category_id' => $cat->id, 'code' => 'B', 'name' => 'ب']);
        $this->vA = $a->versions()->create(['version_code' => 'V1']);
        $this->vB = $b->versions()->create(['version_code' => 'V1']);
    }

    private function purchase(string $method, int $shipping = 900_000): Purchase
    {
        return Purchase::create([
            'warehouse_id' => $this->wh->id, 'order_date' => now(), 'rate_to_irr' => 100_000,
            'shipping_cost' => $shipping, 'allocation_method' => $method,
        ]);
    }

    public function test_weight_method_without_weights_falls_back_to_value(): void
    {
        $p = $this->purchase(Purchase::ALLOCATION_WEIGHT);
        // هر دو ردیف هم‌ارزش و بدون وزن
        $p->items()->create(['item_version_id' => $this->vA->id, 'quantity' => 5, 'fx_unit_price' => 10]);
        $p->items()->create(['item_version_id' => $this->vB->id, 'quantity' => 5, 'fx_unit_price' => 10]);

        app(ReceivePurchase::class)($p);

        $a = $p->items()->where('item_version_id', $this->vA->id)->first();
        $b = $p->items()->where('item_version_id', $this->vB->id)->first();

        // باید مساوی تقسیم شود، نه همه روی ردیف آخر
        $this->assertSame(450_000, $a->allocated_cost);
        $this->assertSame(450_000, $b->allocated_cost);
        $this->assertSame($a->landed_unit_cost, $b->landed_unit_cost, 'دو کالای یکسان باید قیمت تمام‌شده یکسان بگیرند');
    }

    public function test_weight_method_with_real_weights_still_uses_weight(): void
    {
        $p = $this->purchase(Purchase::ALLOCATION_WEIGHT);
        $p->items()->create(['item_version_id' => $this->vA->id, 'quantity' => 10, 'fx_unit_price' => 5, 'weight_kg' => 1]);
        $p->items()->create(['item_version_id' => $this->vB->id, 'quantity' => 10, 'fx_unit_price' => 5, 'weight_kg' => 2]);

        app(ReceivePurchase::class)($p);

        $a = $p->items()->where('item_version_id', $this->vA->id)->first();
        $b = $p->items()->where('item_version_id', $this->vB->id)->first();

        $this->assertSame(300_000, $a->allocated_cost);
        $this->assertSame(600_000, $b->allocated_cost);
    }

    public function test_zero_value_and_zero_weight_splits_equally(): void
    {
        // نرخ ارز صفر → ارزش صفر؛ وزن هم وارد نشده
        $p = Purchase::create([
            'warehouse_id' => $this->wh->id, 'order_date' => now(), 'rate_to_irr' => 0,
            'shipping_cost' => 1_000_000, 'allocation_method' => Purchase::ALLOCATION_WEIGHT,
        ]);
        $p->items()->create(['item_version_id' => $this->vA->id, 'quantity' => 2, 'fx_unit_price' => 10]);
        $p->items()->create(['item_version_id' => $this->vB->id, 'quantity' => 2, 'fx_unit_price' => 10]);

        app(ReceivePurchase::class)($p);

        $a = $p->items()->where('item_version_id', $this->vA->id)->first();
        $b = $p->items()->where('item_version_id', $this->vB->id)->first();

        // به تعداد برمی‌گردد — هر دو ۲ عدد، پس مساوی
        $this->assertSame(500_000, $a->allocated_cost);
        $this->assertSame(500_000, $b->allocated_cost);
    }

    public function test_total_allocated_always_equals_peripheral_cost(): void
    {
        $p = $this->purchase(Purchase::ALLOCATION_WEIGHT, 1_000_003);
        $p->items()->create(['item_version_id' => $this->vA->id, 'quantity' => 3, 'fx_unit_price' => 7]);
        $p->items()->create(['item_version_id' => $this->vB->id, 'quantity' => 4, 'fx_unit_price' => 11]);

        app(ReceivePurchase::class)($p);

        $this->assertSame(1_000_003, (int) $p->items()->sum('allocated_cost'));
    }
}
