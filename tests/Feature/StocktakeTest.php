<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use App\Services\StocktakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * انبارگردانی: فهرست شمارش بدون موجودی، مغایرت‌گیری، و اصلاح با سند.
 */
class StocktakeTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private ItemVersion $trackball;
    private ItemVersion $monitor;
    private StocktakeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create(['name' => 'انباردار', 'email' => 'w@yoursite.com', 'password' => 'secret123']));

        $category = ItemCategory::create(['name' => 'ورودی و کنترل']);
        $trackballItem = Item::create(['item_category_id' => $category->id, 'code' => 'INP-1', 'name' => 'ترکبال بزرگ']);
        $monitorItem = Item::create(['item_category_id' => $category->id, 'code' => 'MON-1', 'name' => 'مانیتور ۲۲ اینچ']);

        $this->trackball = $trackballItem->versions()->create(['version_code' => 'اصلی', 'location' => 'B3']);
        $this->monitor = $monitorItem->versions()->create(['version_code' => 'اصلی', 'location' => 'D2']);

        $this->warehouse = Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);
        $this->service = app(StocktakeService::class);

        $stock = app(StockMovementService::class);
        $stock->recordIn($this->trackball, $this->warehouse, 24, 100, StockMovement::REASON_INITIAL);
        $stock->recordIn($this->monitor, $this->warehouse, 87, 100, StockMovement::REASON_INITIAL);
    }

    public function test_starting_a_stocktake_freezes_the_current_stock(): void
    {
        $stocktake = $this->service->start($this->warehouse);

        $this->assertSame(Stocktake::STATUS_COUNTING, $stocktake->status);
        $this->assertCount(2, $stocktake->lines);
        $this->assertEquals(24, $stocktake->lines->firstWhere('item_version_id', $this->trackball->id)->system_quantity);
    }

    /**
     * موجودی منجمد شده باید منجمد بماند: اگر وسط شمارش کالایی خارج شود،
     * سطر انبارگردانی نباید عوض شود وگرنه مغایرت‌ها دروغ درمی‌آیند.
     */
    public function test_a_movement_during_counting_does_not_move_the_frozen_quantity(): void
    {
        $stocktake = $this->service->start($this->warehouse);

        app(StockMovementService::class)->recordOut(
            $this->trackball, $this->warehouse, 4, StockMovement::REASON_PROJECT,
        );

        $line = $stocktake->lines()->where('item_version_id', $this->trackball->id)->first();

        $this->assertEquals(24, $line->system_quantity);
    }

    /** فهرست شمارش عمداً ستون موجودی ندارد. */
    public function test_the_counting_sheet_hides_the_system_quantity(): void
    {
        $stocktake = $this->service->start($this->warehouse);

        $sheet = $this->service->countingSheet($stocktake);

        $this->assertCount(2, $sheet);
        $this->assertArrayNotHasKey('system_quantity', $sheet[0]);
        $this->assertArrayHasKey('counted', $sheet[0]);
        $this->assertSame('', $sheet[0]['counted']);

        // مطمئن شویم هیچ‌جای سطر عدد موجودی درز نکرده
        $this->assertNotContains('24', array_values($sheet[0]));
        $this->assertNotContains('87', array_values($sheet[0]));
    }

    public function test_the_counting_sheet_carries_the_details_the_counter_needs(): void
    {
        $stocktake = $this->service->start($this->warehouse);
        $row = collect($this->service->countingSheet($stocktake))->firstWhere('code', 'INP-1');

        $this->assertSame('ترکبال بزرگ', $row['name']);
        $this->assertSame('B3', $row['location']);
        $this->assertSame('ورودی و کنترل', $row['category']);
        $this->assertSame('عدد', $row['unit']);
    }

    public function test_a_difference_is_reported_as_shortage_or_surplus(): void
    {
        $stocktake = $this->service->start($this->warehouse);

        $stocktake->lines()->where('item_version_id', $this->trackball->id)->update(['counted_quantity' => 22]);
        $stocktake->lines()->where('item_version_id', $this->monitor->id)->update(['counted_quantity' => 90]);

        $stocktake->refresh()->load('lines');

        $this->assertCount(2, $stocktake->discrepancies());
        $this->assertEquals(2, $stocktake->totalShortage());   // ۲۴ → ۲۲
        $this->assertEquals(3, $stocktake->totalSurplus());    // ۸۷ → ۹۰
    }

    /** سطر شمرده‌نشده مغایرت نیست — «نشمرده» با «برابر» یکی نیست. */
    public function test_an_uncounted_line_is_not_a_discrepancy(): void
    {
        $stocktake = $this->service->start($this->warehouse);
        $stocktake->load('lines');

        $this->assertCount(0, $stocktake->discrepancies());
        $this->assertNull($stocktake->lines->first()->difference());
    }

    /** پایان انبارگردانی فقط می‌بندد و موجودی را دست نمی‌زند. */
    public function test_finishing_closes_without_touching_stock(): void
    {
        $stocktake = $this->service->start($this->warehouse);
        $stocktake->lines()->where('item_version_id', $this->trackball->id)->update(['counted_quantity' => 22]);

        $movementsBefore = StockMovement::count();

        $this->service->finish($stocktake->fresh());

        $this->assertSame(Stocktake::STATUS_CLOSED, $stocktake->fresh()->status);
        $this->assertFalse($stocktake->fresh()->isApplied());
        $this->assertSame($movementsBefore, StockMovement::count());   // موجودی دست‌نخورده
        $this->assertEquals(24, StockBalance::where('item_version_id', $this->trackball->id)->value('quantity'));
    }

    /** به‌روزرسانی انبار با سند اصلاحی موجودی را می‌رساند، نه مستقیم. */
    public function test_applying_adjusts_stock_with_a_movement_document(): void
    {
        $stocktake = $this->service->start($this->warehouse);
        $stocktake->lines()->where('item_version_id', $this->trackball->id)->update(['counted_quantity' => 22]);
        $stocktake->lines()->where('item_version_id', $this->monitor->id)->update(['counted_quantity' => 90]);

        $this->service->finish($stocktake->fresh());
        $movementsBefore = StockMovement::count();

        $result = $this->service->applyToStock($stocktake->fresh());

        $this->assertSame(2, $result['adjusted']);
        $this->assertGreaterThan($movementsBefore, StockMovement::count());
        $this->assertEquals(22, StockBalance::where('item_version_id', $this->trackball->id)->value('quantity'));
        $this->assertEquals(90, StockBalance::where('item_version_id', $this->monitor->id)->value('quantity'));
        $this->assertTrue($stocktake->fresh()->isApplied());
    }

    public function test_the_adjustment_movements_are_marked_as_adjustments(): void
    {
        $stocktake = $this->service->start($this->warehouse);
        $stocktake->lines()->where('item_version_id', $this->trackball->id)->update(['counted_quantity' => 20]);

        $this->service->finish($stocktake->fresh());
        $this->service->applyToStock($stocktake->fresh());

        $adjustment = StockMovement::where('reason', StockMovement::REASON_ADJUSTMENT)->first();

        $this->assertNotNull($adjustment);
        $this->assertSame(Stocktake::class, $adjustment->reference_type);
        $this->assertStringContainsString($stocktake->code, $adjustment->notes);
    }

    /** سطر شمرده‌نشده نباید موجودی را صفر کند. */
    public function test_applying_leaves_uncounted_lines_untouched(): void
    {
        $stocktake = $this->service->start($this->warehouse);
        $stocktake->lines()->where('item_version_id', $this->trackball->id)->update(['counted_quantity' => 24]);

        $this->service->finish($stocktake->fresh());
        $this->service->applyToStock($stocktake->fresh());

        $this->assertEquals(87, StockBalance::where('item_version_id', $this->monitor->id)->value('quantity'));
    }

    public function test_a_finished_stocktake_cannot_be_finished_again(): void
    {
        $stocktake = $this->service->start($this->warehouse);
        $this->service->finish($stocktake->fresh());

        $this->expectException(RuntimeException::class);
        $this->service->finish($stocktake->fresh());
    }

    /** موجودی فقط یک بار به‌روز می‌شود. */
    public function test_stock_cannot_be_applied_twice(): void
    {
        $stocktake = $this->service->start($this->warehouse);
        $stocktake->lines()->where('item_version_id', $this->trackball->id)->update(['counted_quantity' => 20]);
        $this->service->finish($stocktake->fresh());
        $this->service->applyToStock($stocktake->fresh());

        $this->expectException(RuntimeException::class);
        $this->service->applyToStock($stocktake->fresh());
    }

    /** به‌روزرسانی پیش از پایان انبارگردانی مجاز نیست. */
    public function test_stock_cannot_be_applied_before_finishing(): void
    {
        $stocktake = $this->service->start($this->warehouse);
        $stocktake->lines()->where('item_version_id', $this->trackball->id)->update(['counted_quantity' => 20]);

        $this->expectException(RuntimeException::class);
        $this->service->applyToStock($stocktake->fresh());
    }

    public function test_cancelling_stops_the_stocktake_without_changing_stock(): void
    {
        $stocktake = $this->service->start($this->warehouse);
        $movementsBefore = StockMovement::count();

        $this->service->cancel($stocktake->fresh());

        $this->assertSame(Stocktake::STATUS_CANCELLED, $stocktake->fresh()->status);
        $this->assertSame($movementsBefore, StockMovement::count());
        $this->assertFalse($stocktake->fresh()->isEditable());
    }

    public function test_codes_are_sequential(): void
    {
        $first = $this->service->start($this->warehouse);
        $second = $this->service->start($this->warehouse);

        $this->assertNotSame($first->code, $second->code);
        $this->assertStringStartsWith('ANB-', $first->code);
    }
}
