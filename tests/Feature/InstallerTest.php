<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Installation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ویزارد نصب وب (مثل وردپرس): تا نصب نشده هر صفحه به /install می‌رود؛ بعد از
 * نصب، ویزارد قفل می‌شود و به /admin ریدایرکت می‌کند.
 */
class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // قفل نصب نباید از اجرای قبلی مانده باشد
        @unlink(Installation::lockPath());
    }

    protected function tearDown(): void
    {
        @unlink(Installation::lockPath());
        parent::tearDown();
    }

    public function test_a_fresh_site_redirects_everything_to_the_installer(): void
    {
        // دیتابیس خالی است (هیچ مدیری نیست) → نصب‌نشده
        $this->assertFalse(Installation::isInstalled());

        $this->get('/')->assertRedirect('/install');
        $this->get('/admin')->assertRedirect('/install');
    }

    public function test_the_installer_page_loads_when_not_installed(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertSee('نصب سامانه انبارداری', false)
            ->assertSee('اتصال به دیتابیس', false);
    }

    public function test_an_installed_site_sends_the_installer_to_the_app(): void
    {
        // یک مدیر بساز → سامانه «نصب‌شده» حساب می‌شود
        User::create([
            'name' => 'مدیر', 'email' => 'a@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);

        $this->assertTrue(Installation::isInstalled());

        $this->get('/install')->assertRedirect('/admin');
    }

    public function test_the_lock_file_marks_the_site_installed(): void
    {
        $this->assertFalse(Installation::isInstalled());

        Installation::markInstalled();

        $this->assertTrue(Installation::isInstalled());
    }

    /** اطلاعات دیتابیس اشتباه نباید چیزی را خراب کند؛ فقط خطا نشان می‌دهد. */
    public function test_a_bad_database_connection_shows_an_error(): void
    {
        $response = $this->post('/install', [
            'db_host' => '127.0.0.1', 'db_port' => '1', 'db_database' => 'nope',
            'db_username' => 'nobody', 'db_password' => 'x',
            'admin_name' => 'مدیر', 'admin_email' => 'a@dpst.ir', 'admin_password' => 'secret123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('installError');
    }
}
