<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    private ItemVersion $version;
    private Warehouse $main;
    private StockMovementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create(['name' => 'انباردار', 'email' => 'w@dpst.ir', 'password' => 'secret123']);
        $this->actingAs($user);

        $category = ItemCategory::create(['name' => 'ترک‌بال']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'TB-1', 'name' => 'ترک‌بال کوچک']);
        $this->version = ItemVersion::create(['item_id' => $item->id, 'version_code' => '404']);
        $this->main = Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);
        $this->service = app(StockMovementService::class);
    }

    public function test_record_in_creates_lot_and_updates_balance(): void
    {
        $movement = $this->service->recordIn($this->version, $this->main, 10, 500_000, StockMovement::REASON_INITIAL);

        $this->assertSame(StockMovement::DIRECTION_IN, $movement->direction);
        $this->assertSame('10.00', (string) StockLot::first()->quantity_remaining);
        $this->assertEquals(10.0, StockBalance::first()->quantity);
    }

    public function test_record_out_consumes_single_lot_fully(): void
    {
        $this->service->recordIn($this->version, $this->main, 20, 500_000, StockMovement::REASON_INITIAL);

        $movements = $this->service->recordOut($this->version, $this->main, 8, StockMovement::REASON_PROJECT);

        $this->assertCount(1, $movements);
        $this->assertSame(500_000, $movements[0]->unit_cost);
        $this->assertEquals(12.0, StockBalance::first()->quantity);
        $this->assertSame('12.00', (string) StockLot::first()->quantity_remaining);
    }

    public function test_record_out_uses_fifo_across_two_lots(): void
    {
        // لات قدیمی: ۸ عدد به قیمت ۴۰۰٬۰۰۰
        $this->service->recordIn($this->version, $this->main, 8, 400_000, StockMovement::REASON_INITIAL, receivedAt: now()->subDays(10));
        // لات جدید: ۱۲ عدد به قیمت ۵۵۰٬۰۰۰
        $this->service->recordIn($this->version, $this->main, 12, 550_000, StockMovement::REASON_PURCHASE, receivedAt: now());

        // خروج ۱۰ عدد باید ۸ از لات قدیمی + ۲ از لات جدید بردارد
        $movements = $this->service->recordOut($this->version, $this->main, 10, StockMovement::REASON_PROJECT);

        $this->assertCount(2, $movements);
        $this->assertEquals(8.0, (float) $movements[0]->quantity);
        $this->assertSame(400_000, $movements[0]->unit_cost);
        $this->assertEquals(2.0, (float) $movements[1]->quantity);
        $this->assertSame(550_000, $movements[1]->unit_cost);

        $this->assertEquals(10.0, StockBalance::first()->quantity); // 20 - 10
    }

    public function test_record_out_fails_when_insufficient_stock(): void
    {
        $this->service->recordIn($this->version, $this->main, 5, 500_000, StockMovement::REASON_INITIAL);

        $this->expectException(RuntimeException::class);
        $this->service->recordOut($this->version, $this->main, 10, StockMovement::REASON_PROJECT);
    }

    public function test_failed_out_does_not_partially_mutate_balance(): void
    {
        $this->service->recordIn($this->version, $this->main, 5, 500_000, StockMovement::REASON_INITIAL);

        try {
            $this->service->recordOut($this->version, $this->main, 10, StockMovement::REASON_PROJECT);
        } catch (RuntimeException) {
            // مورد انتظار
        }

        $this->assertEquals(5.0, StockBalance::first()->quantity);
        $this->assertSame('5.00', (string) StockLot::first()->quantity_remaining);
    }

    public function test_reserved_quantity_reduces_available_but_not_actual(): void
    {
        $this->service->recordIn($this->version, $this->main, 10, 500_000, StockMovement::REASON_INITIAL);
        StockBalance::first()->update(['reserved' => 4]);

        $balance = StockBalance::first()->fresh();
        $this->assertEquals(10.0, $balance->quantity);
        $this->assertEquals(6.0, $balance->available());

        $this->expectException(RuntimeException::class);
        $this->service->recordOut($this->version, $this->main, 8, StockMovement::REASON_PROJECT);
    }

    public function test_transfer_moves_stock_between_warehouses_preserving_cost(): void
    {
        $consignment = Warehouse::create(['name' => 'امانی نزد مشتری', 'code' => 'CONS', 'type' => Warehouse::TYPE_CONSIGNMENT]);
        $this->service->recordIn($this->version, $this->main, 10, 700_000, StockMovement::REASON_INITIAL);

        $result = $this->service->transfer($this->version, $this->main, $consignment, 4);

        $this->assertEquals(6.0, StockBalance::where('warehouse_id', $this->main->id)->first()->quantity);
        $this->assertEquals(4.0, StockBalance::where('warehouse_id', $consignment->id)->first()->quantity);
        $this->assertSame(700_000, $result['in'][0]->unit_cost);
    }

    public function test_transfer_to_same_warehouse_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->transfer($this->version, $this->main, $this->main, 1);
    }

    public function test_stock_movements_are_never_updated_directly(): void
    {
        $movement = $this->service->recordIn($this->version, $this->main, 5, 500_000, StockMovement::REASON_INITIAL);

        // این تست فقط رفتار مورد انتظار پروژه را مستند می‌کند: هیچ سرویس یا
        // ریسورسی نباید مستقیم ->update() روی StockMovement صدا بزند.
        // اگر لازم بود اصلاح شود، فقط با سند معکوس (recordIn/recordOut دیگر).
        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'quantity' => 5]);
    }
}
