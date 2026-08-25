<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Filament\Resources\Stocktakes\Pages\ViewStocktake;
use App\Filament\Resources\Stocktakes\RelationManagers\LinesRelationManager;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use App\Services\StocktakeService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * صفحه انبارگردانی: رندر جدول گروه‌بندی‌شده با اینپوت درجا و اکشن‌های
 * پایان/به‌روزرسانی/لغو.
 */
class StocktakePageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Stocktake $stocktake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::create([
            'name' => 'مدیر', 'email' => 'admin@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $this->admin->assignRole(User::TYPE_ADMIN);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        $category = ItemCategory::create(['name' => 'ورودی و کنترل']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'INP-1', 'name' => 'ترکبال بزرگ']);
        $version = $item->versions()->create(['version_code' => 'اصلی', 'location' => 'B3']);

        $warehouse = Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);
        app(StockMovementService::class)->recordIn($version, $warehouse, 24, 0, StockMovement::REASON_INITIAL);

        $this->stocktake = app(StocktakeService::class)->start($warehouse);
    }

    public function test_the_view_page_renders_with_finish_and_cancel(): void
    {
        Livewire::test(ViewStocktake::class, ['record' => $this->stocktake->getKey()])
            ->assertSuccessful()
            ->assertActionExists('finish')
            ->assertActionExists('cancel')
            // به‌روزرسانی انبار تا وقتی بسته نشده دیده نمی‌شود
            ->assertActionHidden('applyToStock');
    }

    /** جدول سطرها با گروه‌بندی دسته و اینپوت درجا باید بدون خطا رندر شود. */
    public function test_the_lines_table_renders_grouped_by_category(): void
    {
        Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $this->stocktake,
            'pageClass'   => ViewStocktake::class,
        ])
            ->assertSuccessful()
            ->assertSee('ترکبال بزرگ')
            ->assertSee('ورودی و کنترل');   // عنوان گروه دسته
    }

    public function test_finishing_then_applying_updates_stock(): void
    {
        $this->stocktake->lines()->update(['counted_quantity' => 20]);

        Livewire::test(ViewStocktake::class, ['record' => $this->stocktake->getKey()])
            ->callAction('finish', ['understood' => true])
            ->assertHasNoActionErrors();

        $this->assertSame(Stocktake::STATUS_CLOSED, $this->stocktake->fresh()->status);

        Livewire::test(ViewStocktake::class, ['record' => $this->stocktake->getKey()])
            ->assertActionVisible('applyToStock')
            ->callAction('applyToStock', ['understood' => true])
            ->assertHasNoActionErrors();

        $this->assertTrue($this->stocktake->fresh()->isApplied());
        $this->assertEquals(20, \App\Models\StockBalance::first()->quantity);
    }

    public function test_a_stocktake_can_be_deleted(): void
    {
        Livewire::test(\App\Filament\Resources\Stocktakes\Pages\ListStocktakes::class)
            ->assertTableActionVisible('delete', $this->stocktake)
            ->callTableAction('delete', $this->stocktake)
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted('stocktakes', ['id' => $this->stocktake->id]);
    }

    public function test_a_user_without_the_stocktake_permission_cannot_finish(): void
    {
        $staff = User::create([
            'name' => 'کارشناس', 'email' => 'staff@dpst.ir',
            'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $staff->syncPermissions([Permission::ViewStock->value]);
        $this->actingAs($staff);

        Livewire::test(ViewStocktake::class, ['record' => $this->stocktake->getKey()])
            ->assertActionHidden('finish')
            ->assertActionHidden('cancel');
    }
}
