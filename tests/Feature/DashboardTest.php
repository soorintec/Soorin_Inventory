<?php

namespace Tests\Feature;

use App\Enums\Permission as Perm;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * داشبورد. تا پیش از این خالی بود و هیچ چیزی نشان نمی‌داد.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::create([
            'name' => 'مدیر انبار', 'email' => 'admin@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $this->admin->forceFill(['last_login_at' => now()])->save();

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        $category = ItemCategory::create(['name' => 'کابل و رابط']);
        $warehouse = Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);

        $stocked = Item::create(['item_category_id' => $category->id, 'code' => 'CBL-1', 'name' => 'کابل VGA']);
        $version = $stocked->versions()->create(['version_code' => 'اصلی', 'min_stock' => 5]);
        app(StockMovementService::class)->recordIn($version, $warehouse, 21, 0, StockMovement::REASON_INITIAL);

        $empty = Item::create(['item_category_id' => $category->id, 'code' => 'CBL-2', 'name' => 'کابل HDMI']);
        $empty->versions()->create(['version_code' => 'اصلی', 'min_stock' => 3]);
    }

    public function test_the_dashboard_loads(): void
    {
        $this->get('/admin')->assertOk();
    }

    public function test_the_overview_shows_the_key_numbers(): void
    {
        // قیمت ریالی روی ورژنِ دارای موجودی تا ارزش موجودی محاسبه شود
        \App\Models\ItemVersion::where('version_code', 'اصلی')
            ->whereHas('item', fn ($q) => $q->where('code', 'CBL-1'))
            ->update(['fx_price' => 100_000, 'fx_currency' => 'IRR']);

        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Widgets\WarehouseOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('کالا')
            ->assertSee('ارزش موجودی')          // به جای «مجموع موجودی»
            ->assertSee('۲٬۱۰۰٬۰۰۰ ریال')       // ۲۱ × ۱۰۰٬۰۰۰ به تفکیک ارز
            ->assertSee('کالای تمام‌شده')
            ->assertSee('آخرین انبارگردانی');    // تاریخ آخرین انبارگردانی
    }

    public function test_the_low_stock_box_lists_the_empty_item(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Widgets\LowStockWidget::class)
            ->assertSuccessful()
            ->assertSee('کابل HDMI')      // موجودی صفر
            ->assertDontSee('کابل VGA');  // موجودی کافی
    }

    public function test_the_recent_logins_box_shows_who_signed_in_and_when(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Widgets\RecentLoginsWidget::class)
            ->assertSuccessful()
            ->assertSee('آخرین ورودها به سامانه')
            ->assertSee('مدیر انبار');
    }

    public function test_the_activity_box_shows_recent_changes(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Filament\Widgets\RecentActivityWidget::class)
            ->assertSuccessful()
            ->assertSee('ورود کالا')
            ->assertSee('مدیر انبار');
    }

    /** باکس ورودها اطلاعات مدیریتی است و برای همه نباید دیده شود. */
    public function test_the_logins_box_is_hidden_without_the_users_permission(): void
    {
        $staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);

        $this->actingAs($staff);

        $this->assertFalse(\App\Filament\Widgets\RecentLoginsWidget::canView());
        $this->assertTrue(\App\Filament\Widgets\LowStockWidget::canView());
    }

    public function test_widgets_are_hidden_from_a_user_without_stock_access(): void
    {
        $outsider = User::create([
            'name' => 'بیرونی', 'email' => 'x@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $outsider->syncPermissions([Perm::ViewReports->value]);

        $this->actingAs($outsider->fresh());

        $this->assertFalse(\App\Filament\Widgets\WarehouseOverviewWidget::canView());
        $this->assertFalse(\App\Filament\Widgets\LowStockWidget::canView());
        $this->assertFalse(\App\Filament\Widgets\RecentActivityWidget::canView());
    }
}
