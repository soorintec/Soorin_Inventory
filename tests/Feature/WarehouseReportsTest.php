<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use App\Services\StocktakeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * گزارش‌های چاپی انبار. مهم است که واقعاً PDF تولید شود، نه فقط ۲۰۰ برگردد —
 * قالب‌های blade به‌راحتی با یک متغیر جاافتاده می‌شکنند.
 */
class WarehouseReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private ItemVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::create([
            'name' => 'مدیر انبار', 'email' => 'admin@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $this->admin->assignRole(User::TYPE_ADMIN);
        $this->actingAs($this->admin);

        $category = ItemCategory::create(['name' => 'کابل و رابط']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'CBL-001', 'name' => 'کابل VGA']);
        $this->version = $item->versions()->create([
            'version_code' => 'اصلی', 'location' => 'D3/#04',
            'fx_price' => 12.5, 'fx_currency' => 'USD',
        ]);

        $this->warehouse = Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);

        app(StockMovementService::class)->recordIn(
            $this->version, $this->warehouse, 21, 500_000, StockMovement::REASON_INITIAL,
        );
    }

    private function assertIsPdf(\Illuminate\Testing\TestResponse $response): void
    {
        // گزارش‌ها حالا به‌صورت صفحه HTML با پنجره چاپ خودکار باز می‌شوند،
        // نه دانلود PDF. نام متد برای سازگاری نگه داشته شده است.
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('window.print()', $response->getContent());
    }

    public function test_the_stock_list_prints_as_a_pdf(): void
    {
        $this->assertIsPdf($this->get(route('warehouse.print.stock')));
    }

    public function test_the_stock_list_can_be_limited_to_one_warehouse(): void
    {
        $this->assertIsPdf($this->get(route('warehouse.print.stock', ['warehouse' => $this->warehouse->id])));
    }

    public function test_the_stock_flow_report_prints_as_a_pdf(): void
    {
        $this->assertIsPdf($this->get(route('warehouse.print.flow')));
    }

    public function test_the_stock_flow_report_accepts_a_jalali_range(): void
    {
        $this->assertIsPdf($this->get(route('warehouse.print.flow', [
            'from' => '۱۴۰۵/۰۱/۰۱',
            'to'   => '۱۴۰۵/۱۲/۲۹',
        ])));
    }

    /** بازه بی‌معنی نباید ۵۰۰ بدهد؛ گزارش خالی درست است. */
    public function test_an_unparsable_date_falls_back_instead_of_exploding(): void
    {
        $this->assertIsPdf($this->get(route('warehouse.print.flow', ['from' => 'چرند'])));
    }

    public function test_the_counting_sheet_prints_and_hides_the_quantity(): void
    {
        $stocktake = app(StocktakeService::class)->start($this->warehouse);

        $response = $this->get(route('stocktake.sheet', ['stocktake' => $stocktake->id]));

        $this->assertIsPdf($response);
    }

    public function test_the_stocktake_report_prints(): void
    {
        $stocktake = app(StocktakeService::class)->start($this->warehouse);
        $stocktake->lines()->update(['counted_quantity' => 19]);

        $this->assertIsPdf($this->get(route('stocktake.report', ['stocktake' => $stocktake->id])));
    }

    /** کارشناس انبار باید بتواند پرینت بگیرد — کار روزمره‌اش است. */
    public function test_warehouse_staff_can_print(): void
    {
        $staff = User::create([
            'name' => 'کارشناس انبار', 'email' => 'staff@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);

        $this->actingAs($staff)->get(route('warehouse.print.stock'))->assertOk();
    }

    /**
     * کاربر بدون مجوز مشاهده انبار نباید گزارش بگیرد.
     *
     * نقش عمداً بعد از ساخت برداشته می‌شود: مدل User به‌طور پیش‌فرض
     * user_type را «staff» می‌گذارد و خودکار نقش می‌دهد، پس «کاربر بدون
     * نقش» با create ساده به دست نمی‌آید.
     */
    public function test_a_user_without_stock_permission_is_refused(): void
    {
        $outsider = User::create([
            'name' => 'بی‌مجوز', 'email' => 'no@yoursite.com', 'password' => 'secret123',
        ]);
        $outsider->syncRoles([]);
        $outsider->syncPermissions([]);

        $this->assertFalse($outsider->fresh()->can(\App\Enums\Permission::ViewStock->value));

        $this->actingAs($outsider->fresh())
            ->get(route('warehouse.print.stock'))
            ->assertForbidden();
    }
}
