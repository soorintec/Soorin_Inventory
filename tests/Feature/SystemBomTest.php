<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockMovement;
use App\Models\SystemModel;
use App\Models\SystemVersion;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تعریف سامانه و قطعاتش — «برای ساخت یک دستگاه چه لازم است، چقدرش را داریم،
 * و چند دستگاه می‌توانیم بسازیم».
 */
class SystemBomTest extends TestCase
{
    use RefreshDatabase;

    private SystemVersion $version;
    private Warehouse $warehouse;
    private ItemVersion $trackball;
    private ItemVersion $monitor;
    private StockMovementService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create(['name' => 'انباردار', 'email' => 'w@yoursite.com', 'password' => 'secret123']));

        $category = ItemCategory::create(['name' => 'ورودی و کنترل']);
        $trackballItem = Item::create(['item_category_id' => $category->id, 'code' => 'INP-1', 'name' => 'ترکبال کوچک']);
        $monitorItem = Item::create(['item_category_id' => $category->id, 'code' => 'MON-1', 'name' => 'مانیتور ۲۲ اینچ']);

        $this->trackball = $trackballItem->versions()->create(['version_code' => '404']);
        $this->monitor = $monitorItem->versions()->create(['version_code' => 'اصلی']);

        $this->warehouse = Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);
        $this->stock = app(StockMovementService::class);

        $model = SystemModel::create(['code' => 'TITAN', 'name' => 'Titan S2']);
        $this->version = $model->versions()->create(['version_code' => '1404']);
    }

    private function addPart(ItemVersion $version, float $quantity, bool $optional = false): void
    {
        $this->version->bomLines()->create([
            'item_id'         => $version->item_id,
            'item_version_id' => $version->id,
            'quantity'        => $quantity,
            'is_optional'     => $optional,
        ]);
    }

    private function stockIn(ItemVersion $version, float $quantity, int $unitCost): void
    {
        $this->stock->recordIn($version, $this->warehouse, $quantity, $unitCost, StockMovement::REASON_INITIAL);
    }

    public function test_shortage_is_the_gap_between_required_and_stock(): void
    {
        $this->addPart($this->trackball, 4);
        $this->stockIn($this->trackball, 3, 100);

        $line = $this->version->bomLines()->first();

        $this->assertEquals(3, $line->currentStock());
        $this->assertEquals(1, $line->shortage());
    }

    public function test_there_is_no_shortage_when_stock_covers_the_requirement(): void
    {
        $this->addPart($this->trackball, 4);
        $this->stockIn($this->trackball, 10, 100);

        $this->assertEquals(0, $this->version->bomLines()->first()->shortage());
    }

    /** قیمت واحد باید از قدیمی‌ترین لات بیاید، چون FIFO همان را مصرف می‌کند. */
    public function test_unit_cost_comes_from_the_oldest_remaining_lot(): void
    {
        $this->addPart($this->trackball, 2);
        $this->stockIn($this->trackball, 5, 800_000);
        $this->stockIn($this->trackball, 5, 950_000);

        $line = $this->version->bomLines()->first();

        $this->assertSame(800_000, $line->unitCost());
        $this->assertSame(1_600_000, $line->lineCost());
    }

    public function test_estimated_cost_sums_the_required_lines(): void
    {
        $this->addPart($this->trackball, 2);
        $this->addPart($this->monitor, 1);
        $this->stockIn($this->trackball, 10, 800_000);
        $this->stockIn($this->monitor, 10, 5_000_000);

        $this->assertSame(6_600_000, $this->version->fresh()->estimatedCost());   // ۲×۸۰۰ه + ۵ میلیون
    }

    /** قطعه اختیاری در همه دستگاه‌ها نیست، پس نه در قیمت می‌آید نه در کسری. */
    public function test_optional_parts_are_excluded_from_cost_and_shortage(): void
    {
        $this->addPart($this->trackball, 2);
        $this->addPart($this->monitor, 1, optional: true);
        $this->stockIn($this->trackball, 10, 800_000);
        // برای مانیتور هیچ موجودی ثبت نمی‌شود؛ اگر اختیاری درست کار کند، کسری نمی‌سازد

        $version = $this->version->fresh();

        $this->assertSame(1_600_000, $version->estimatedCost());
        $this->assertSame(0, $version->shortageCount());
    }

    public function test_buildable_units_are_limited_by_the_scarcest_part(): void
    {
        $this->addPart($this->trackball, 2);     // موجودی ۱۰ ← ۵ دستگاه
        $this->addPart($this->monitor, 1);       // موجودی ۳  ← ۳ دستگاه
        $this->stockIn($this->trackball, 10, 100);
        $this->stockIn($this->monitor, 3, 100);

        $this->assertSame(3, $this->version->fresh()->buildableUnits());
    }

    public function test_buildable_is_zero_when_a_required_part_is_missing(): void
    {
        $this->addPart($this->trackball, 2);
        $this->addPart($this->monitor, 1);
        $this->stockIn($this->trackball, 10, 100);

        $this->assertSame(0, $this->version->fresh()->buildableUnits());
        $this->assertSame(1, $this->version->fresh()->shortageCount());
    }

    /** بدون ورژن مشخص، هر ورژنی از آن کالا قابل استفاده است. */
    public function test_a_line_without_a_version_counts_every_version_of_the_item(): void
    {
        $second = $this->trackball->item->versions()->create(['version_code' => '405']);

        $this->version->bomLines()->create([
            'item_id'  => $this->trackball->item_id,
            'quantity' => 5,
        ]);

        $this->stockIn($this->trackball, 4, 100);
        $this->stockIn($second, 3, 100);

        $line = $this->version->bomLines()->first();

        $this->assertEquals(7, $line->currentStock());
        $this->assertEquals(0, $line->shortage());
    }

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'مدیر', 'email' => 'a@yoursite.com', 'password' => 'secret123',
            'user_type' => User::TYPE_ADMIN,
        ]);
        $admin->assignRole(User::TYPE_ADMIN);

        return $admin;
    }

    public function test_the_parts_page_loads(): void
    {
        $this->addPart($this->trackball, 4);
        $this->stockIn($this->trackball, 3, 100);

        $this->actingAs($this->admin())
            ->get("/admin/system-versions/{$this->version->id}")
            ->assertOk()
            ->assertSee('Titan S2');
    }

    /**
     * جدول قطعات یک relation manager است و با درخواست Livewire جدا رندر
     * می‌شود، پس در HTML اولیه صفحه نیست و باید مستقیم تست شود.
     */
    public function test_the_parts_table_shows_each_part_with_its_stock_and_shortage(): void
    {
        $this->addPart($this->trackball, 4);
        $this->stockIn($this->trackball, 3, 100);

        \Filament\Facades\Filament::setCurrentPanel('admin');

        \Livewire\Livewire::actingAs($this->admin())->test(
            \App\Filament\Resources\SystemVersions\RelationManagers\BomRelationManager::class,
            [
                'ownerRecord' => $this->version,
                'pageClass'   => \App\Filament\Resources\SystemVersions\Pages\ViewSystemVersion::class,
            ],
        )
            ->assertSuccessful()
            ->assertSee('ترکبال کوچک')
            ->assertSee('۴')      // موردنیاز
            ->assertSee('۳')      // موجودی
            ->assertSee('۱');     // کسری
    }

    public function test_a_part_can_be_added_from_the_parts_table(): void
    {
        $admin = $this->admin();

        // setUp با یک کاربر بدون نقش وارد شده؛ دکمه افزودن به مجوز
        // system_models.manage وابسته است، پس باید واقعاً به‌عنوان مدیر باشیم.
        $this->actingAs($admin);
        $this->assertTrue($admin->can(\App\Enums\Permission::ManageSystemModels->value));

        \Filament\Facades\Filament::setCurrentPanel('admin');

        \Livewire\Livewire::actingAs($admin)->test(
            \App\Filament\Resources\SystemVersions\RelationManagers\BomRelationManager::class,
            [
                'ownerRecord' => $this->version,
                'pageClass'   => \App\Filament\Resources\SystemVersions\Pages\ViewSystemVersion::class,
            ],
        )
            ->callAction(\Filament\Actions\Testing\TestAction::make('create')->table(), [
                'item_id'         => $this->trackball->item_id,
                'item_version_id' => $this->trackball->id,
                'quantity'        => 6,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $this->version->bomLines()->count());
        $this->assertEquals(6, $this->version->bomLines()->first()->quantity);
    }
}
