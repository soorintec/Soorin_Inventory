<?php

namespace Tests\Feature;

use App\Enums\Permission as Perm;
use App\Filament\Pages\BrandingSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * شخصی‌سازیِ برند: نام کسب‌وکار و لوگو باید از settings خوانده شوند و به پیش‌فرضِ
 * config برگردند وقتی تنظیم نشده‌اند.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');

        $this->admin = User::create([
            'name' => 'مدیر', 'email' => 'admin@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $this->admin->syncPermissions(Perm::values());
    }

    public function test_falls_back_to_config_defaults_when_unset(): void
    {
        $this->assertSame(config('branding.company.name'), Branding::companyName());
        $this->assertSame(config('branding.app.title'), Branding::appTitle());
        $this->assertSame(config('branding.company.website_label'), Branding::websiteLabel());
    }

    public function test_settings_override_the_defaults(): void
    {
        Setting::set('branding.company_name', 'شرکت آزمایشی', 'branding');
        Setting::set('branding.app_title', 'سامانه آزمایشی', 'branding');

        $this->assertSame('شرکت آزمایشی', Branding::companyName());
        $this->assertSame('سامانه آزمایشی', Branding::appTitle());
    }

    public function test_logo_falls_back_to_default_asset(): void
    {
        $this->assertStringContainsString('images/logo-light.png', Branding::logo('light'));
        $this->assertFalse(Branding::hasCustomLogo('light'));
    }

    public function test_custom_logo_is_served_from_branding_disk(): void
    {
        Storage::fake('branding');
        Storage::disk('branding')->put('logos/custom.svg', '<svg></svg>');
        Setting::set('branding.logo_light', 'logos/custom.svg', 'branding', 'file');

        $this->assertTrue(Branding::hasCustomLogo('light'));
        $this->assertStringContainsString('logos/custom.svg', Branding::logo('light'));
    }

    public function test_page_requires_settings_permission(): void
    {
        $staff = User::create([
            'name' => 'کارشناس', 'email' => 's@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_STAFF,
        ]);
        $staff->syncPermissions([Perm::ViewStock->value]);

        $this->actingAs($staff);
        $this->assertFalse(BrandingSettings::canAccess());

        $this->actingAs($this->admin);
        $this->assertTrue(BrandingSettings::canAccess());
    }

    public function test_admin_can_save_branding_via_the_page(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BrandingSettings::class)
            ->fillForm([
                'company_name'    => 'شرکت نمونه',
                'app_title'       => 'انبار نمونه',
                'company_name_en' => 'Sample Co',
                'website'         => 'https://example.test',
                'website_label'   => 'example.test',
                'founded_year'    => 1401,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('شرکت نمونه', Branding::companyName());
        $this->assertSame('انبار نمونه', Branding::appTitle());
        $this->assertSame('example.test', Branding::websiteLabel());
        $this->assertSame(1401, Branding::foundedYear());
    }

    public function test_reset_restores_defaults(): void
    {
        Setting::set('branding.company_name', 'موقتی', 'branding');
        $this->assertSame('موقتی', Branding::companyName());

        Livewire::actingAs($this->admin)
            ->test(BrandingSettings::class)
            ->call('resetToDefaults');

        $this->assertSame(config('branding.company.name'), Branding::companyName());
        $this->assertSame(0, Setting::where('group', 'branding')->count());
    }
}
