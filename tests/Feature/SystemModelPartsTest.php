<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Filament\Resources\SystemModels\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\SystemModels\Pages\ViewSystemModel;
use App\Filament\Resources\SystemVersions\Pages\ViewSystemVersion;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\SystemModel;
use App\Models\SystemVersion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * انتخاب قطعات مدل سامانه هنگام ساخت نسخه، و دسترسی صفحه قطعات.
 */
class SystemModelPartsTest extends TestCase
{
    use RefreshDatabase;

    private SystemModel $model;
    private Item $part;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');

        $category = ItemCategory::create(['name' => 'ورودی و کنترل']);
        $this->part = Item::create(['item_category_id' => $category->id, 'code' => 'INP-1', 'name' => 'ترکبال بزرگ']);
        $this->model = SystemModel::create(['code' => 'TITAN', 'name' => 'Titan S2']);
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'مدیر', 'email' => 'admin@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN]);
        // hook کاربر خودش مجوزها را می‌دهد؛ فقط کش اسپتی را تازه می‌کنیم تا
        // اولین تست هم مجوزها را ببیند (وگرنه بسته به ترتیب اجرا نوسان دارد).
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    /** ساخت نسخه با قطعاتش در همان فرم — قطعات باید ذخیره شوند. */
    public function test_a_version_can_be_created_with_its_parts_inline(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(VersionsRelationManager::class, [
            'ownerRecord' => $this->model,
            'pageClass'   => ViewSystemModel::class,
        ])
            ->callTableAction('create', data: [
                'version_code' => 'نسخه ۱',
                'bomLines'     => [
                    ['item_id' => $this->part->id, 'quantity' => 4, 'is_optional' => false],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $version = SystemVersion::where('version_code', 'نسخه ۱')->firstOrFail();
        $this->assertCount(1, $version->bomLines);
        $this->assertEquals(4, $version->bomLines->first()->quantity);
        $this->assertEquals($this->part->id, $version->bomLines->first()->item_id);
    }

    /** صفحه قطعات نسخه باید برای مدیر باز شود (باگ «دسترسی ندارم»). */
    public function test_the_parts_page_opens_for_a_manager(): void
    {
        $this->actingAs($this->admin());
        $version = $this->model->versions()->create(['version_code' => 'نسخه ۱']);

        $this->get("/admin/system-versions/{$version->id}")->assertOk();

        // اکشن افزودن قطعه باید برای مدیر دیده شود
        Livewire::test(\App\Filament\Resources\SystemVersions\RelationManagers\BomRelationManager::class, [
            'ownerRecord' => $version,
            'pageClass'   => ViewSystemVersion::class,
        ])->assertSuccessful()->assertTableActionVisible('create');
    }

    /** بازدیدکننده بدون مجوز مدیریت، قطعات را می‌بیند ولی افزودن نمی‌بیند. */
    public function test_a_viewer_sees_parts_read_only(): void
    {
        $staff = User::create(['name' => 'کارشناس', 'email' => 's@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_STAFF]);
        $staff->syncPermissions([Permission::ViewProjects->value]);
        $this->actingAs($staff);

        $version = $this->model->versions()->create(['version_code' => 'نسخه ۱']);

        $this->get("/admin/system-versions/{$version->id}")->assertOk();

        Livewire::test(\App\Filament\Resources\SystemVersions\RelationManagers\BomRelationManager::class, [
            'ownerRecord' => $version,
            'pageClass'   => ViewSystemVersion::class,
        ])->assertSuccessful()->assertTableActionHidden('create');
    }
}
