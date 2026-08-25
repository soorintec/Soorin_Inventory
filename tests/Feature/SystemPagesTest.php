<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSystem;
use App\Models\Project;
use App\Models\SystemModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemPagesTest extends TestCase
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

    public function test_system_models_page_loads(): void
    {
        SystemModel::create(['code' => 'TITAN', 'name' => 'Titan S2']);
        $this->get('/admin/system-models')->assertOk()->assertSee('Titan S2');
    }

    public function test_system_model_view_page_loads(): void
    {
        $model = SystemModel::create(['code' => 'TITAN', 'name' => 'Titan S2']);
        $this->get("/admin/system-models/{$model->id}")->assertOk();
    }

    public function test_projects_page_loads(): void
    {
        $this->get('/admin/projects')->assertOk();
    }

    public function test_create_project_page_loads(): void
    {
        Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $this->get('/admin/projects/create')->assertOk();
    }

    public function test_edit_project_page_loads(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $project = Project::create(['code' => 'P1', 'title' => 'تست', 'customer_id' => $customer->id]);
        $this->get("/admin/projects/{$project->id}/edit")->assertOk();
    }

    public function test_customer_systems_page_loads(): void
    {
        $this->get('/admin/customer-systems')->assertOk();
    }

    public function test_edit_customer_system_page_loads(): void
    {
        $customer = Customer::create(['code' => 'ARIA', 'name' => 'شرکت آریا']);
        $system = CustomerSystem::create(['code' => 'CS1', 'customer_id' => $customer->id, 'name' => 'سالن']);
        $this->get("/admin/customer-systems/{$system->id}/edit")->assertOk();
    }
}
