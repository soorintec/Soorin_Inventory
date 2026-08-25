<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\Project;
use App\Models\ProjectChecklistLine;
use App\Models\StockMovement;
use App\Models\SystemModel;
use App\Models\SystemVersion;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProjectChecklistService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectChecklistServiceTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private Item $trackball;
    private ItemVersion $tb404;
    private SystemVersion $systemVersion;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create(['name' => 'انباردار', 'email' => 'w@dpst.ir', 'password' => 'secret123']);
        $this->actingAs($user);

        $this->warehouse = Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);

        $category = ItemCategory::create(['name' => 'ترک‌بال']);
        $this->trackball = Item::create(['item_category_id' => $category->id, 'code' => 'TB', 'name' => 'ترک‌بال کوچک']);
        $this->tb404 = $this->trackball->versions()->create(['version_code' => '404']);

        $model = SystemModel::create(['code' => 'TITAN', 'name' => 'Titan S2']);
        $this->systemVersion = $model->versions()->create(['version_code' => '1404']);

        // BOM: هر Titan به ۴ ترک‌بال ورژن ۴۰۴ نیاز دارد
        $this->systemVersion->bomLines()->create([
            'item_id' => $this->trackball->id, 'item_version_id' => $this->tb404->id, 'quantity' => 4,
        ]);

        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $this->project = Project::create([
            'code' => 'PRJ-1', 'title' => 'سالن کنترل آریا', 'customer_id' => $customer->id,
            'system_version_id' => $this->systemVersion->id,
        ]);
    }

    public function test_generate_creates_checklist_from_bom(): void
    {
        app(ProjectChecklistService::class)->generateFromBom($this->project);

        $lines = $this->project->checklistLines()->get();
        $this->assertCount(1, $lines);
        $this->assertEquals(4.0, $lines->first()->quantity_required);
    }

    public function test_full_stock_reserves_everything_no_shortage(): void
    {
        app(StockMovementService::class)->recordIn($this->tb404, $this->warehouse, 10, 500_000, StockMovement::REASON_INITIAL);

        app(ProjectChecklistService::class)->generateFromBom($this->project);

        $line = $this->project->checklistLines()->first();
        $this->assertEquals(4.0, $line->quantity_reserved);
        $this->assertEquals(0.0, $line->quantity_shortage);
        $this->assertSame(ProjectChecklistLine::STATUS_RESERVED, $line->status);
    }

    public function test_partial_stock_shows_shortage(): void
    {
        // فقط ۳ عدد موجود، نیاز ۴ عدد → کسری ۱
        app(StockMovementService::class)->recordIn($this->tb404, $this->warehouse, 3, 500_000, StockMovement::REASON_INITIAL);

        app(ProjectChecklistService::class)->generateFromBom($this->project);

        $line = $this->project->checklistLines()->first();
        $this->assertEquals(3.0, $line->quantity_reserved);
        $this->assertEquals(1.0, $line->quantity_shortage);
        $this->assertSame(ProjectChecklistLine::STATUS_PURCHASE_NEEDED, $line->status);
    }

    public function test_no_stock_shows_full_shortage(): void
    {
        app(ProjectChecklistService::class)->generateFromBom($this->project);

        $line = $this->project->checklistLines()->first();
        $this->assertEquals(0.0, $line->quantity_reserved);
        $this->assertEquals(4.0, $line->quantity_shortage);
        $this->assertSame(ProjectChecklistLine::STATUS_PURCHASE_NEEDED, $line->status);
    }

    public function test_reserved_stock_is_not_counted_as_available(): void
    {
        app(StockMovementService::class)->recordIn($this->tb404, $this->warehouse, 5, 500_000, StockMovement::REASON_INITIAL);
        // ۳ عدد رزرو پروژه دیگر
        \App\Models\StockBalance::where('item_version_id', $this->tb404->id)->first()->update(['reserved' => 3]);

        app(ProjectChecklistService::class)->generateFromBom($this->project);

        // فقط ۲ عدد آزاد → رزرو ۲، کسری ۲
        $line = $this->project->checklistLines()->first();
        $this->assertEquals(2.0, $line->quantity_reserved);
        $this->assertEquals(2.0, $line->quantity_shortage);
    }

    public function test_regenerate_replaces_previous_lines(): void
    {
        app(ProjectChecklistService::class)->generateFromBom($this->project);
        app(ProjectChecklistService::class)->generateFromBom($this->project);

        // نباید خطوط تکراری بسازد
        $this->assertCount(1, $this->project->checklistLines()->get());
    }
}
