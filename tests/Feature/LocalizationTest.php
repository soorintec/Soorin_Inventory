<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Jalali;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * چندزبانه‌بودن سامانه: برابری کلیدهای ترجمه، تعویض زبان، و آگاهیِ تاریخ/عدد از زبان.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    /** هر زبانِ تعریف‌شده باید دقیقاً همان کلیدهای فارسی را داشته باشد — تا چیزی جا نیفتد. */
    public function test_all_locales_have_the_same_keys_as_persian(): void
    {
        $faFiles = glob(lang_path('fa/*.php'));
        $this->assertNotEmpty($faFiles);

        $others = array_diff(array_keys(config('locales.available')), ['fa']);
        $this->assertNotEmpty($others, 'حداقل یک زبان دیگر باید تعریف شده باشد.');

        foreach ($others as $locale) {
            foreach ($faFiles as $faFile) {
                $name = basename($faFile);
                $file = lang_path("{$locale}/{$name}");

                $this->assertFileExists($file, "ترجمهٔ «{$locale}» برای {$name} وجود ندارد.");

                $this->assertSame(
                    $this->flatten(require $faFile),
                    $this->flatten(require $file),
                    "کلیدهای {$name} بین fa و {$locale} یکسان نیستند.",
                );
            }
        }
    }

    public function test_english_translations_load(): void
    {
        app()->setLocale('en');

        $this->assertSame('Save', __('common.save'));
        $this->assertSame('Warehouse stock', __('items.nav_label'));
        $this->assertSame('View items', __('permissions.labels')['items.view']);
    }

    public function test_persian_is_the_default(): void
    {
        app()->setLocale('fa');

        $this->assertSame('ذخیره', __('common.save'));
    }

    public function test_digits_follow_the_locale(): void
    {
        app()->setLocale('fa');
        $this->assertSame('۱۲۳', Jalali::digits('123'));

        app()->setLocale('en');
        $this->assertSame('123', Jalali::digits('123'));
    }

    public function test_arabic_uses_arabic_indic_digits_and_gregorian_dates(): void
    {
        app()->setLocale('ar');

        $this->assertSame('١٢٣', Jalali::digits('123'));                 // ارقام عربی‌هندی
        // تقویم میلادی، ولی ارقام عربی‌هندی
        $this->assertSame('٢٠٢٦-٠٨-١٣', Jalali::format(Carbon::parse('2026-08-13')));
        $this->assertSame('rtl', config('locales.available.ar.dir'));    // راست‌به‌چپ
        $this->assertSame('العربية', config('locales.available.ar.name'));
    }

    public function test_arabic_translations_load(): void
    {
        app()->setLocale('ar');

        $this->assertSame('حفظ', __('common.save'));
        $this->assertSame('عرض الأصناف', __('permissions.labels')['items.view']);
    }

    public function test_dates_are_jalali_in_fa_and_gregorian_in_en(): void
    {
        $date = Carbon::parse('2026-08-13 10:00:00');

        app()->setLocale('fa');
        $fa = Jalali::format($date);
        $this->assertStringContainsString('۱۴۰۵', $fa); // سال شمسی با ارقام فارسی

        app()->setLocale('en');
        $en = Jalali::format($date);
        $this->assertSame('2026-08-13', $en); // میلادی با ارقام لاتین
    }

    public function test_switching_locale_saves_to_the_user_and_session(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::create([
            'name' => 'کاربر', 'email' => 'u@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);

        $this->actingAs($user)
            ->from('/admin')
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect();

        $this->assertSame('en', $user->fresh()->locale);
        $this->assertSame('en', session('locale'));
    }

    public function test_switching_locale_does_not_log_the_user_out(): void
    {
        // رگرسیون: قبلاً تعویض زبان کاربر را از حساب بیرون می‌انداخت. نوار بالا
        // و صفحهٔ پنل با AuthenticateSession محافظت می‌شوند؛ مطمئن می‌شویم بعد از
        // POST /locale نشستِ ورود سالم می‌ماند و صفحهٔ محافظت‌شده هنوز باز می‌شود.
        $this->seed(RolePermissionSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel('admin');

        $user = User::create([
            'name' => 'مدیر', 'email' => 'stay@example.com', 'password' => 'secret123',
            'user_type' => User::TYPE_ADMIN,
        ]);
        $user->syncPermissions(\App\Enums\Permission::values());

        // شبیه‌سازی وضعیتی که AuthenticateSession پس از ورود در نشست می‌گذارد.
        session()->put('password_hash_web', $user->getAuthPassword());

        $this->actingAs($user)
            ->from('/admin')
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect();

        $this->assertAuthenticated();

        // درخواست بعدی به صفحهٔ محافظت‌شده نباید به صفحهٔ ورود بازگردد.
        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_panel_renders_in_english_for_an_english_user(): void
    {
        $this->seed(RolePermissionSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel('admin');

        $user = User::create([
            'name' => 'Admin', 'email' => 'en@yoursite.com', 'password' => 'secret123',
            'user_type' => User::TYPE_ADMIN, 'locale' => 'en',
        ]);
        $user->syncPermissions(\App\Enums\Permission::values());

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
        $response->assertSee('Warehouse stock');   // items.nav_label به انگلیسی
        $response->assertSee('dir="ltr"', false);  // جهت خودکار چپ‌به‌راست شد
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::create([
            'name' => 'کاربر', 'email' => 'u2@yoursite.com', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);

        $this->actingAs($user)
            ->post('/locale', ['locale' => 'xx']) // زبانِ تعریف‌نشده
            ->assertSessionHasErrors('locale');

        $this->assertSame('fa', $user->fresh()->locale);
    }

    /**
     * تبدیل آرایهٔ تودرتوی زبان به فهرست کلیدهای نقطه‌ای مرتب.
     *
     * @return array<int, string>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $keys = is_array($value) ? array_merge($keys, $this->flatten($value, $full)) : array_merge($keys, [$full]);
        }

        sort($keys);

        return $keys;
    }
}
