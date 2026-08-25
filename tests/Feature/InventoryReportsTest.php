<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSystem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryReportExcelService;
use App\Services\InventoryReportService;
use App\Services\StockMovementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportsTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private ItemVersion $version;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::create(['name' => 'انباردار', 'email' => 'w@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN]);
        $this->user->assignRole(User::TYPE_ADMIN);
        $this->actingAs($this->user);

        $this->warehouse = Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);
        $category = ItemCategory::create(['name' => 'مانیتور']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'MON', 'name' => 'مانیتور ۲۲']);
        $this->version = $item->versions()->create(['version_code' => 'A1']);
    }

    public function test_movements_by_user_counts_correctly(): void
    {
        app(StockMovementService::class)->recordIn($this->version, $this->warehouse, 10, 500_000, StockMovement::REASON_INITIAL);
        app(StockMovementService::class)->recordOut($this->version, $this->warehouse, 3, StockMovement::REASON_PROJECT);

        $report = app(InventoryReportService::class)->generate(now()->subDay(), now()->addDay());

        $this->assertCount(1, $report['by_user']);
        $row = $report['by_user']->first();
        $this->assertSame('انباردار', $row['user']);
        $this->assertSame(1, $row['in_count']);
        $this->assertSame(1, $row['out_count']);
        $this->assertEquals(10.0, $row['in_qty']);
        $this->assertEquals(3.0, $row['out_qty']);
    }

    public function test_stock_levels_reflect_current_quantity_and_value(): void
    {
        // ارزش از قیمت خود ورژن می‌آید (نه قیمت لات)، به ارز همان ورژن
        $this->version->update(['fx_price' => 750_000, 'fx_currency' => 'IRR']);
        app(StockMovementService::class)->recordIn($this->version, $this->warehouse, 8, 0, StockMovement::REASON_INITIAL);

        $report = app(InventoryReportService::class)->generate(now()->subDay(), now()->addDay());

        $this->assertCount(1, $report['stock_levels']);
        $row = $report['stock_levels']->first();
        $this->assertEquals(8.0, $row['qty']);
        $this->assertEquals(6_000_000, $row['value']); // ۸ × ۷۵۰٬۰۰۰ ریال
        $this->assertSame('ریال', $row['currency_label']);

        // ارزش کل هم به تفکیک ارز درست جمع می‌شود
        $this->assertEquals(6_000_000, $report['stock_value']['IRR']['value']);
    }

    public function test_system_costs_listed(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        CustomerSystem::create(['code' => 'CS-1', 'customer_id' => $customer->id, 'name' => 'سالن', 'total_cost' => 45_000_000]);

        $report = app(InventoryReportService::class)->generate(now()->subDay(), now()->addDay());

        $this->assertCount(1, $report['system_costs']);
        $this->assertSame(45_000_000, $report['system_costs']->first()['cost']);
    }

    public function test_reports_page_loads(): void
    {
        $this->get('/admin/reports')->assertOk();
    }

    public function test_excel_export_downloads(): void
    {
        app(StockMovementService::class)->recordIn($this->version, $this->warehouse, 5, 500_000, StockMovement::REASON_INITIAL);

        $this->get(route('reports.export.excel', ['from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_pdf_export_opens_as_a_print_preview(): void
    {
        app(StockMovementService::class)->recordIn($this->version, $this->warehouse, 5, 500_000, StockMovement::REASON_INITIAL);

        // به‌جای دانلود PDF، صفحه HTML با پنجره چاپ خودکار باز می‌شود
        $response = $this->get(route('reports.export.pdf', ['from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString()]));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('window.print()', $response->getContent());
        $this->assertStringContainsString(__('reports.pdf_title'), $response->getContent());
    }

    public function test_excel_has_three_sheets(): void
    {
        $report = app(InventoryReportService::class)->generate(now()->subDay(), now()->addDay());
        $spreadsheet = app(InventoryReportExcelService::class)->build($report);

        $this->assertSame(3, $spreadsheet->getSheetCount());
    }

    public function test_pdf_view_has_no_untranslated_keys(): void
    {
        $report = app(InventoryReportService::class)->generate(now()->subDay(), now()->addDay());

        $html = view('pdf.report', [
            'report' => $report, 'company' => config('branding.company'),
            'money'  => fn (int $a) => \App\Support\Jalali::money($a),
            'date'   => fn ($d) => \App\Support\Jalali::format($d),
            'digits' => fn ($n) => \App\Support\Jalali::digits((string) $n),
        ])->render();

        $this->assertDoesNotMatchRegularExpression('/\breports\.[a-z_]+\b/', $html);
    }
}
