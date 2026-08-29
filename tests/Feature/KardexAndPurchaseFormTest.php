<?php

namespace Tests\Feature;

use App\Filament\Resources\Items\Pages\ItemKardex;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Models\Currency;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KardexAndPurchaseFormTest extends TestCase
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

    private function makeVersion(): ItemVersion
    {
        $cat = ItemCategory::create(['name' => 'کنترل']);
        $item = Item::create(['item_category_id' => $cat->id, 'code' => 'INP-1', 'name' => 'ترک‌بال بزرگ']);

        return $item->versions()->create(['version_code' => 'اصلی', 'fx_price' => 10, 'fx_currency' => 'USD']);
    }

    private function move(ItemVersion $v, string $direction, float $qty, string $reason = StockMovement::REASON_ADJUSTMENT): void
    {
        StockMovement::create([
            'item_version_id' => $v->id,
            'warehouse_id'    => $this->warehouse->id,
            'direction'       => $direction,
            'reason'          => $reason,
            'quantity'        => $qty,
            'unit_cost'       => 0,
            'user_id'         => $this->admin->id,
        ]);
    }

    /** کاردکس: ماندهٔ در حرکت درست محاسبه می‌شود (۱۰ ورود − ۳ خروج = ۷). */
    public function test_kardex_computes_running_balance(): void
    {
        $v = $this->makeVersion();
        $this->move($v, StockMovement::DIRECTION_IN, 10, StockMovement::REASON_PURCHASE);
        $this->move($v, StockMovement::DIRECTION_OUT, 3, StockMovement::REASON_PROJECT);

        Livewire::actingAs($this->admin)
            ->test(ItemKardex::class, ['record' => $v->item_id])
            ->assertSuccessful()
            ->assertSet('balance', 7.0);
    }

    /** کالای بدون حرکت: مانده صفر و پیام «تراکنشی ثبت نشده». */
    public function test_kardex_is_empty_for_an_item_without_movements(): void
    {
        $v = $this->makeVersion();

        Livewire::actingAs($this->admin)
            ->test(ItemKardex::class, ['record' => $v->item_id])
            ->assertSuccessful()
            ->assertSet('balance', 0.0)
            ->assertSee(__('stock.kardex_empty'));
    }

    /** رگرسیون: هنگام «ساختِ خرید» باید بتوان کالا را انتخاب کرد (ردیف‌ها در خودِ فرم). */
    public function test_a_purchase_can_be_created_with_items_from_the_form(): void
    {
        $v = $this->makeVersion();
        $supplier = Supplier::create(['name' => 'تأمین‌کننده']);
        $currency = Currency::firstOrCreate(['code' => 'USD'], ['name' => 'دلار']);

        Livewire::actingAs($this->admin)
            ->test(CreatePurchase::class)
            ->fillForm([
                'supplier_id'       => $supplier->id,
                'warehouse_id'      => $this->warehouse->id,
                'type'              => 'import',
                'order_date'        => now()->toDateString(),
                'currency_id'       => $currency->id,
                'rate_to_irr'       => 50000,
                'allocation_method' => 'value',
                'items'             => [
                    ['item_version_id' => $v->id, 'quantity' => 2, 'fx_unit_price' => 10, 'weight_kg' => 1.5],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $purchase = Purchase::first();
        $this->assertNotNull($purchase);
        $this->assertCount(1, $purchase->items);
        $this->assertSame($v->id, $purchase->items->first()->item_version_id);
        $this->assertEqualsWithDelta(2, (float) $purchase->items->first()->quantity, 0.001);
    }
}
