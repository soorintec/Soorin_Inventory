<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $admin = User::create(['name' => 'مدیر', 'email' => 'admin@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN]);
        $admin->assignRole(User::TYPE_ADMIN);
        $this->actingAs($admin);
    }

    public function test_suppliers_page_loads(): void
    {
        Supplier::create(['name' => 'تأمین‌کننده چین']);
        $this->get('/admin/suppliers')->assertOk()->assertSee('تأمین‌کننده چین');
    }

    public function test_currencies_page_loads(): void
    {
        Currency::create(['code' => 'CNY', 'name' => 'یوان']);
        $this->get('/admin/currencies')->assertOk()->assertSee('CNY');
    }

    public function test_purchases_page_loads(): void
    {
        $this->get('/admin/purchases')->assertOk();
    }

    public function test_create_purchase_page_loads(): void
    {
        Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);
        $this->get('/admin/purchases/create')->assertOk();
    }

    public function test_edit_purchase_page_loads(): void
    {
        $warehouse = Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);
        $purchase = Purchase::create(['warehouse_id' => $warehouse->id, 'order_date' => now(), 'rate_to_irr' => 500_000]);

        $this->get("/admin/purchases/{$purchase->id}/edit")->assertOk();
    }
}
