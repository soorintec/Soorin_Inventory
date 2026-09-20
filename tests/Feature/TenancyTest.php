<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * پیِ چند-کسب‌وکاری (فاز ۱): رجیستریِ کسب‌وکار، اتصالِ tenant، و سوییچرِ کسب‌وکار.
 */
class TenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_default_business_exists_after_migration(): void
    {
        $default = Business::default();

        $this->assertNotNull($default);
        $this->assertTrue($default->is_default);
        $this->assertSame('', (string) $default->table_prefix);
        $this->assertNull($default->database);
    }

    public function test_operational_models_use_the_tenant_connection(): void
    {
        $this->assertSame('tenant', (new Item)->getConnectionName());
        $this->assertSame('tenant', (new \App\Models\Warehouse)->getConnectionName());
        $this->assertSame('tenant', (new \App\Models\Customer)->getConnectionName());
    }

    public function test_users_without_explicit_access_fall_back_to_all_active_businesses(): void
    {
        $user = User::create([
            'name' => 'کاربر', 'email' => 'u@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);

        // بدونِ ردیفِ business_user، باید همهٔ کسب‌وکارهای فعال در دسترس باشند.
        $this->assertTrue($user->accessibleBusinesses()->contains('id', Business::default()->id));
    }

    public function test_switching_business_stores_an_accessible_business_in_session(): void
    {
        $user = User::create([
            'name' => 'مدیر', 'email' => 'a@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $second = Business::create(['name' => 'کسب‌وکار دوم', 'table_prefix' => 'b2_', 'is_active' => true]);
        $user->businesses()->attach([Business::default()->id, $second->id]);

        $this->actingAs($user)
            ->post('/business', ['business' => $second->id])
            ->assertRedirect();

        $this->assertSame($second->id, session('active_business_id'));
    }

    public function test_switching_to_an_inaccessible_business_is_ignored(): void
    {
        $user = User::create([
            'name' => 'مدیر', 'email' => 'b@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        // فقط به کسب‌وکارِ اصلی دسترسی دارد.
        $user->businesses()->attach(Business::default()->id);
        $other = Business::create(['name' => 'کسب‌وکار غیرمجاز', 'table_prefix' => 'b9_', 'is_active' => true]);

        $this->actingAs($user)
            ->post('/business', ['business' => $other->id])
            ->assertRedirect();

        $this->assertNull(session('active_business_id'));
    }

    public function test_creating_an_item_still_works_under_the_default_business(): void
    {
        // رفتارِ تک‌کسب‌وکاری دست‌نخورده: ساخت کالا روی اتصالِ tenant (=همان دیتابیس).
        $category = ItemCategory::create(['name' => 'دسته']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'X-1', 'name' => 'کالای تست']);

        $this->assertDatabaseHas('items', ['code' => 'X-1']);
        $this->assertSame('tenant', $item->getConnectionName());
    }
}
