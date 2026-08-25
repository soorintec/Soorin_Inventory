<?php

namespace Tests\Feature;

use App\Enums\Permission as Perm;
use App\Filament\Pages\LicensePage;
use App\Models\Setting;
use App\Models\User;
use App\Support\License;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * لایسنسِ آفلاینِ امضاشده: تأیید امضا، انقضا، مهلت→قفل، فعال‌سازی، و هدایتِ قفل.
 */
class LicenseTest extends TestCase
{
    use RefreshDatabase;

    private string $publicHex;
    private string $secretHex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');

        // جفت‌کلیدِ آزمایشی؛ کلید عمومی در config می‌نشیند تا برنامه تأیید کند.
        $keypair = sodium_crypto_sign_keypair();
        $this->publicHex = bin2hex(sodium_crypto_sign_publickey($keypair));
        $this->secretHex = bin2hex(sodium_crypto_sign_secretkey($keypair));
        config(['license.public_key' => $this->publicHex]);
        config(['license.grace_days' => 14]);
        // شناسهٔ سخت‌افزارِ ثابت برای تست، مستقل از ماشین.
        config(['license.hwid_override' => 'TEST-HWID-0001']);
    }

    private function makeKey(array $payload = []): string
    {
        $payload = array_merge([
            'licensed_to' => 'شرکت آزمایشی',
            'hwid'        => '',
            'edition'     => 'standard',
            'issued_at'   => now()->toIso8601String(),
            'expires_at'  => null,
        ], $payload);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sig  = sodium_crypto_sign_detached($json, hex2bin($this->secretHex));

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=') . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    }

    private function admin(): User
    {
        $u = User::create([
            'name' => 'مدیر', 'email' => 'a@dpst.ir', 'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $u->syncPermissions(Perm::values());

        return $u;
    }

    public function test_a_properly_signed_key_is_valid(): void
    {
        $check = License::verify($this->makeKey());

        $this->assertTrue($check['valid']);
        $this->assertSame('شرکت آزمایشی', $check['payload']['licensed_to']);
    }

    public function test_empty_and_malformed_keys_are_rejected(): void
    {
        $this->assertFalse(License::verify('')['valid']);
        $this->assertSame('no_key', License::verify('')['reason']);
        $this->assertFalse(License::verify('garbage')['valid']);
        $this->assertSame('malformed', License::verify('garbage')['reason']);
    }

    public function test_a_tampered_key_fails_the_signature(): void
    {
        $key = $this->makeKey(['licensed_to' => 'شرکت آزمایشی']);
        // دستکاریِ payload بدون امضای تازه → امضا باید رد شود.
        [$p, $sig] = explode('.', $key);
        $forgedPayload = rtrim(strtr(base64_encode('{"licensed_to":"هکر","domain":"","expires_at":null}'), '+/', '-_'), '=');

        $check = License::verify($forgedPayload . '.' . $sig);

        $this->assertFalse($check['valid']);
        $this->assertSame('bad_signature', $check['reason']);
    }

    public function test_an_expired_key_is_rejected(): void
    {
        $check = License::verify($this->makeKey(['expires_at' => now()->subDay()->toIso8601String()]));

        $this->assertFalse($check['valid']);
        $this->assertSame('expired', $check['reason']);
    }

    public function test_a_key_locked_to_this_hardware_is_valid_but_another_is_rejected(): void
    {
        // قفل‌شده به HWID همین سرور → معتبر
        $this->assertTrue(License::verify($this->makeKey(['hwid' => 'TEST-HWID-0001']))['valid']);

        // قفل‌شده به سخت‌افزار دیگر → رد
        $other = License::verify($this->makeKey(['hwid' => 'OTHER-HWID-9999']));
        $this->assertFalse($other['valid']);
        $this->assertSame('hwid_mismatch', $other['reason']);
    }

    public function test_without_a_key_it_starts_in_grace_then_locks(): void
    {
        $status = License::status();
        $this->assertFalse($status['licensed']);
        $this->assertTrue($status['in_grace']);
        $this->assertFalse($status['locked']);

        // مهلت را به گذشته ببر → قفل
        Setting::set('license.grace_started_at', now()->subDays(30)->toIso8601String(), 'license');

        $this->assertTrue(License::isLocked());
    }

    public function test_pulling_the_clock_back_locks_the_app(): void
    {
        // انگار برنامه قبلاً تاریخی در آینده دیده (کاربر ساعت را عقب کشیده).
        Setting::set('license.clock_high_water', now()->addDays(30)->toIso8601String(), 'license');

        $status = License::status();

        $this->assertTrue($status['clock_tampered']);
        $this->assertTrue($status['locked']);
        $this->assertSame('clock_tampered', $status['reason']);
    }

    public function test_a_valid_license_is_not_affected_by_clock_rollback(): void
    {
        Setting::set('license.clock_high_water', now()->addDays(30)->toIso8601String(), 'license');
        License::store($this->makeKey(['hwid' => 'TEST-HWID-0001']));

        $status = License::status();

        $this->assertTrue($status['licensed']);
        $this->assertFalse($status['locked']);
    }

    public function test_rollback_cannot_revive_an_expired_key(): void
    {
        // کلید دیروز منقضی شده؛ کاربر ساعت را به قبل از انقضا عقب می‌کشد.
        License::store($this->makeKey(['expires_at' => now()->subDay()->toIso8601String()]));
        Setting::set('license.clock_high_water', now()->toIso8601String(), 'license');

        // حتی اگر now عقب برود، effectiveNow از high_water می‌آید → همچنان منقضی.
        $this->assertFalse(License::isLicensed());
    }

    public function test_licensed_install_stops_tracking_the_clock(): void
    {
        License::store($this->makeKey(['hwid' => 'TEST-HWID-0001']));

        License::status(); // نباید high_water بسازد چون لایسنس معتبر است

        $this->assertNull(Setting::get('license.clock_high_water'));
    }

    public function test_admin_can_activate_a_valid_key(): void
    {
        $key = $this->makeKey();

        Livewire::actingAs($this->admin())
            ->test(LicensePage::class)
            ->fillForm(['key' => $key])
            ->call('activate');

        $this->assertTrue(License::isLicensed());
        $this->assertSame($key, Setting::get('license.key'));
    }

    public function test_locked_panel_redirects_to_the_license_page(): void
    {
        Setting::set('license.grace_started_at', now()->subDays(30)->toIso8601String(), 'license');

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertRedirect(LicensePage::getUrl());
    }

    public function test_a_valid_license_unlocks_the_panel(): void
    {
        Setting::set('license.grace_started_at', now()->subDays(30)->toIso8601String(), 'license');
        License::store($this->makeKey());

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk();
    }
}
