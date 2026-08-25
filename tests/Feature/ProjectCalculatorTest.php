<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Filament\Pages\ProjectCalculator;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockBalance;
use App\Models\SystemModel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProjectCalculatorService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Item $monitor;
    private array $versionIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $category = ItemCategory::create(['name' => 'نمایشگر']);
        $this->monitor = Item::create(['item_category_id' => $category->id, 'code' => 'MON-8', 'name' => 'مانیتور ۸ اینچ']);
        // قیمت ۵۰۰ دلار، موجودی ۸ عدد
        $version = $this->monitor->versions()->create(['version_code' => 'اصلی', 'fx_price' => 500, 'fx_currency' => 'USD']);
        $warehouse = Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);
        StockBalance::create(['item_version_id' => $version->id, 'warehouse_id' => $warehouse->id, 'quantity' => 8]);

        // s1=۳ مانیتور، s2=۲، s3=۱
        foreach (['s1' => 3, 's2' => 2, 's3' => 1] as $name => $perUnit) {
            $model = SystemModel::create(['code' => strtoupper($name), 'name' => $name]);
            $sv = $model->versions()->create(['version_code' => 'اصلی']);
            $sv->bomLines()->create(['item_id' => $this->monitor->id, 'quantity' => $perUnit]);
            $this->versionIds[$name] = $sv->id;
        }
    }

    /** ۱×s1 + ۲×s2 + ۳×s3 = ۳ + ۴ + ۳ = ۱۰ مانیتور. */
    public function test_it_sums_parts_across_selected_systems(): void
    {
        $result = app(ProjectCalculatorService::class)->calculate([
            ['system_version_id' => $this->versionIds['s1'], 'quantity' => 1],
            ['system_version_id' => $this->versionIds['s2'], 'quantity' => 2],
            ['system_version_id' => $this->versionIds['s3'], 'quantity' => 3],
        ]);

        $this->assertCount(1, $result['rows']);
        $row = $result['rows'][0];

        $this->assertSame('مانیتور ۸ اینچ', $row['item']);
        $this->assertEqualsWithDelta(10, $row['required'], 0.001);
    }

    /** قیمت = تعداد موردنیاز × قیمت واحد، مستقل از موجودی (۱۰ × ۵۰۰ = ۵۰۰۰، نه ۸ × ۵۰۰). */
    public function test_price_uses_required_quantity_not_stock(): void
    {
        $result = app(ProjectCalculatorService::class)->calculate([
            ['system_version_id' => $this->versionIds['s1'], 'quantity' => 1],
            ['system_version_id' => $this->versionIds['s2'], 'quantity' => 2],
            ['system_version_id' => $this->versionIds['s3'], 'quantity' => 3],
        ]);

        $this->assertEqualsWithDelta(5000, $result['rows'][0]['line_total'], 0.001);
        $this->assertEqualsWithDelta(5000, $result['totals']['USD']['value'], 0.001);
    }

    /** وضعیت موجودی: نیاز ۱۰، موجودی ۸ → ۲ عدد کسری. */
    public function test_it_reports_shortage_against_stock(): void
    {
        $result = app(ProjectCalculatorService::class)->calculate([
            ['system_version_id' => $this->versionIds['s1'], 'quantity' => 1],
            ['system_version_id' => $this->versionIds['s2'], 'quantity' => 2],
            ['system_version_id' => $this->versionIds['s3'], 'quantity' => 3],
        ]);

        $this->assertEqualsWithDelta(8, $result['rows'][0]['stock'], 0.001);
        $this->assertEqualsWithDelta(2, $result['rows'][0]['shortage'], 0.001);
    }

    public function test_totals_are_split_per_currency(): void
    {
        // یک قطعهٔ ریالی هم به s1 اضافه می‌کنیم
        $cable = Item::create(['item_category_id' => $this->monitor->item_category_id, 'code' => 'CBL-1', 'name' => 'کابل']);
        $cable->versions()->create(['version_code' => 'اصلی', 'fx_price' => 200000, 'fx_currency' => 'IRR']);
        SystemModel::query(); // no-op
        \App\Models\SystemVersion::find($this->versionIds['s1'])
            ->bomLines()->create(['item_id' => $cable->id, 'quantity' => 4]);

        $result = app(ProjectCalculatorService::class)->calculate([
            ['system_version_id' => $this->versionIds['s1'], 'quantity' => 2],
        ]);

        // ۲×s1: مانیتور ۶ عدد × ۵۰۰ = ۳۰۰۰ دلار؛ کابل ۸ عدد × ۲۰۰۰۰۰ = ۱٬۶۰۰٬۰۰۰ ریال
        $this->assertEqualsWithDelta(3000, $result['totals']['USD']['value'], 0.001);
        $this->assertEqualsWithDelta(1_600_000, $result['totals']['IRR']['value'], 0.001);
        // ترتیب خروجی همیشه ریال، دلار (بعد یوان) است
        $this->assertSame(['IRR', 'USD'], array_keys($result['totals']));
    }

    public function test_the_page_renders_for_a_project_viewer(): void
    {
        Filament::setCurrentPanel('admin');

        $admin = User::create(['name' => 'مدیر', 'email' => 'a@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN]);

        Livewire::actingAs($admin)
            ->test(ProjectCalculator::class)
            ->assertSuccessful()
            ->assertSee('ماشین‌حساب پروژه');
    }

    public function test_access_requires_project_view_permission(): void
    {
        $outsider = User::create(['name' => 'x', 'email' => 'x@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_STAFF]);
        $outsider->syncPermissions([Permission::ViewStock->value]);
        $this->actingAs($outsider);

        $this->assertFalse(ProjectCalculator::canAccess());
    }
}
