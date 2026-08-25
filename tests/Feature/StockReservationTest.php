<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\Project;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\SystemModel;
use App\Models\SystemVersion;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProjectChecklistService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * رزرو موجودی باید واقعاً روی انبار قفل شود — وگرنه دو پروژه همزمان روی
 * یک کالا حساب باز می‌کنند (خواسته صریح سند پروژه).
 */
class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private Item $item;
    private ItemVersion $version;
    private SystemVersion $systemVersion;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create(['name' => 'انباردار', 'email' => 'w@dpst.ir', 'password' => 'secret123']);
        $this->actingAs($user);

        $this->warehouse = Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);
        $category = ItemCategory::create(['name' => 'ترک‌بال']);
        $this->item = Item::create(['item_category_id' => $category->id, 'code' => 'TB', 'name' => 'ترک‌بال']);
        $this->version = $this->item->versions()->create(['version_code' => '404']);

        $model = SystemModel::create(['code' => 'T', 'name' => 'Titan']);
        $this->systemVersion = $model->versions()->create(['version_code' => '1404']);

        $this->customer = Customer::create(['code' => 'ARIA', 'name' => 'آریا']);
    }

    private function bom(float $qty): void
    {
        $this->systemVersion->bomLines()->create([
            'item_id' => $this->item->id, 'item_version_id' => $this->version->id, 'quantity' => $qty,
        ]);
    }

    private function project(string $code): Project
    {
        return Project::create([
            'code' => $code, 'title' => 'پروژه ' . $code, 'customer_id' => $this->customer->id,
            'system_version_id' => $this->systemVersion->id,
        ]);
    }

    private function stockIn(float $qty): void
    {
        app(StockMovementService::class)->recordIn($this->version, $this->warehouse, $qty, 500_000, StockMovement::REASON_INITIAL);
    }

    public function test_reservation_is_written_to_stock_balance(): void
    {
        $this->stockIn(10);
        $this->bom(3);

        app(ProjectChecklistService::class)->generateFromBom($this->project('A'));

        $this->assertEquals(3.0, StockBalance::first()->reserved);
        $this->assertEquals(7.0, StockBalance::first()->available());
    }

    public function test_second_project_cannot_double_book_same_stock(): void
    {
        $this->stockIn(4);
        $this->bom(4);

        $service = app(ProjectChecklistService::class);
        $service->generateFromBom($projectA = $this->project('A'));
        $service->generateFromBom($projectB = $this->project('B'));

        // پروژه الف همه ۴ عدد را گرفت؛ پروژه ب باید کسری کامل ببیند
        $this->assertEquals(4.0, $projectA->checklistLines()->first()->quantity_reserved);
        $this->assertEquals(0.0, $projectB->checklistLines()->first()->quantity_reserved);
        $this->assertEquals(4.0, $projectB->checklistLines()->first()->quantity_shortage);
    }

    public function test_regenerating_checklist_does_not_double_reserve(): void
    {
        $this->stockIn(10);
        $this->bom(3);

        $service = app(ProjectChecklistService::class);
        $project = $this->project('A');
        $service->generateFromBom($project);
        $service->generateFromBom($project);

        $this->assertEquals(3.0, StockBalance::first()->reserved);
    }

    public function test_cancelling_project_releases_reservation(): void
    {
        $this->stockIn(10);
        $this->bom(4);

        $project = $this->project('A');
        app(ProjectChecklistService::class)->generateFromBom($project);
        $this->assertEquals(4.0, StockBalance::first()->reserved);

        $project->update(['status' => Project::STATUS_CANCELLED]);

        $this->assertEquals(0.0, StockBalance::first()->fresh()->reserved);
    }

    public function test_deleting_project_releases_reservation(): void
    {
        $this->stockIn(10);
        $this->bom(4);

        $project = $this->project('A');
        app(ProjectChecklistService::class)->generateFromBom($project);

        $project->delete();

        $this->assertEquals(0.0, StockBalance::first()->fresh()->reserved);
    }

    public function test_reserved_stock_cannot_be_issued_out(): void
    {
        $this->stockIn(5);
        $this->bom(5);

        app(ProjectChecklistService::class)->generateFromBom($this->project('A'));

        // همه ۵ عدد رزرو پروژه است — خروج برای کار دیگر باید رد شود
        $this->expectException(RuntimeException::class);
        app(StockMovementService::class)->recordOut($this->version, $this->warehouse, 1, StockMovement::REASON_ADJUSTMENT);
    }
}
