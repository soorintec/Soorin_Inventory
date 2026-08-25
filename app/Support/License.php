<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * لایسنسِ آفلاین و امضاشده (Ed25519).
 *
 * کلید لایسنس = base64url(payload_json) . "." . base64url(signature)
 * که فروشنده با کلید خصوصی‌اش امضا می‌کند و برنامه با کلید عمومیِ داخلِ config
 * تأییدش می‌کند — بدون نیاز به اینترنت، پس روی سرور داخلی هم کار می‌کند.
 *
 * قفلِ دامنه: هر کلید به یک دامنه/آی‌پی بسته می‌شود تا روی چند سرور کپی نشود.
 * مهلت: تا چند روز اول بدون لایسنس کار می‌کند (بنر هشدار)، بعد پنل قفل می‌شود.
 */
class License
{
    public const GROUP = 'license';

    /**
     * وضعیت کامل لایسنس.
     *
     * @return array{
     *   licensed: bool, reason: ?string, licensed_to: ?string, domain: ?string,
     *   expires_at: ?string, in_grace: bool, grace_ends_at: ?\Illuminate\Support\Carbon,
     *   grace_days_left: int, locked: bool
     * }
     */
    public static function status(): array
    {
        $base = [
            'licensed' => false, 'reason' => null, 'licensed_to' => null, 'hwid' => null,
            'expires_at' => null, 'in_grace' => false, 'grace_ends_at' => null,
            'grace_days_left' => 0, 'locked' => false, 'clock_tampered' => false,
        ];

        $check = self::verify((string) Setting::get(self::GROUP . '.key'));

        if ($check['valid']) {
            // لایسنسِ معتبر (دائمی یا هنوز منقضی‌نشده) — پس از فعال‌سازی، دیگر ساعت
            // را مانیتور نمی‌کنیم و اصلاً لمسش نمی‌کنیم.
            return array_merge($base, [
                'licensed'    => true,
                'licensed_to' => $check['payload']['licensed_to'] ?? null,
                'hwid'        => $check['payload']['hwid'] ?? null,
                'expires_at'  => $check['payload']['expires_at'] ?? null,
            ]);
        }

        // فقط وقتی لایسنس نیست: ساعت را «لمس» می‌کنیم (بیشترین تاریخِ دیده‌شده) تا اگر
        // کاربر ساعت را عقب بکشد (برای دورزدنِ مهلت)، فهمیده شود.
        $tampered = self::touchClock();
        $effectiveNow = self::effectiveNow();
        $graceEnds = self::graceEndsAt();
        $locked = $tampered || $effectiveNow->greaterThan($graceEnds);

        return array_merge($base, [
            'reason'          => $tampered ? 'clock_tampered' : $check['reason'],
            'in_grace'        => ! $locked,
            'grace_ends_at'   => $graceEnds,
            'grace_days_left' => $locked ? 0 : (int) ceil($effectiveNow->floatDiffInDays($graceEnds)),
            'locked'          => $locked,
            'clock_tampered'  => $tampered,
        ]);
    }

    /**
     * بیشترین تاریخِ دیده‌شده را به‌روز می‌کند و می‌گوید آیا ساعت به‌طور مشکوک
     * عقب کشیده شده است (بیش از چند روزِ رواداری، برای مصون‌ماندن از NTP/زمان‌بندی).
     */
    private static function touchClock(): bool
    {
        $now = now();
        $raw = Setting::get(self::GROUP . '.clock_high_water');
        $tolerance = (int) config('license.clock_tolerance_days', 2);
        $tampered = false;

        if (filled($raw)) {
            $high = Carbon::parse($raw);

            if ($now->lessThan($high->copy()->subDays($tolerance))) {
                $tampered = true;
            }

            if ($now->greaterThan($high)) {
                Setting::set(self::GROUP . '.clock_high_water', $now->toIso8601String(), self::GROUP);
            }
        } else {
            Setting::set(self::GROUP . '.clock_high_water', $now->toIso8601String(), self::GROUP);
        }

        return $tampered;
    }

    /** بیشینهٔ «اکنون» و «بالاترین تاریخِ دیده‌شده» — تا عقب‌کشیدنِ ساعت مهلت را تمدید نکند. */
    private static function effectiveNow(): Carbon
    {
        $raw = Setting::get(self::GROUP . '.clock_high_water');
        $now = now();

        return filled($raw) && Carbon::parse($raw)->greaterThan($now) ? Carbon::parse($raw) : $now;
    }

    public static function isLicensed(): bool
    {
        return self::verify((string) Setting::get(self::GROUP . '.key'))['valid'];
    }

    public static function isLocked(): bool
    {
        return self::status()['locked'];
    }

    /**
     * تأیید یک کلید لایسنس.
     *
     * @return array{valid: bool, reason: ?string, payload: array<string, mixed>}
     */
    public static function verify(string $key): array
    {
        $key = trim($key);

        if ($key === '') {
            return ['valid' => false, 'reason' => 'no_key', 'payload' => []];
        }

        $publicKeyHex = (string) config('license.public_key');

        if ($publicKeyHex === '' || ! ctype_xdigit($publicKeyHex)) {
            return ['valid' => false, 'reason' => 'no_public_key', 'payload' => []];
        }

        $parts = explode('.', $key);

        if (count($parts) !== 2) {
            return ['valid' => false, 'reason' => 'malformed', 'payload' => []];
        }

        $json = self::b64urlDecode($parts[0]);
        $sig  = self::b64urlDecode($parts[1]);

        if ($json === false || $sig === false) {
            return ['valid' => false, 'reason' => 'malformed', 'payload' => []];
        }

        try {
            $ok = sodium_crypto_sign_verify_detached($sig, $json, hex2bin($publicKeyHex));
        } catch (\Throwable) {
            return ['valid' => false, 'reason' => 'bad_signature', 'payload' => []];
        }

        if (! $ok) {
            return ['valid' => false, 'reason' => 'bad_signature', 'payload' => []];
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            return ['valid' => false, 'reason' => 'malformed', 'payload' => []];
        }

        // انقضا (کلید دائمی expires_at ندارد) — با زمانِ مؤثر تا عقب‌کشیدنِ ساعت،
        // کلیدِ منقضی را دوباره زنده نکند.
        if (! empty($payload['expires_at']) && self::effectiveNow()->greaterThan(Carbon::parse($payload['expires_at']))) {
            return ['valid' => false, 'reason' => 'expired', 'payload' => $payload];
        }

        // قفلِ سخت‌افزار — کلید به Hardware ID این سرور بسته است.
        if (! self::hwidMatches($payload['hwid'] ?? null)) {
            return ['valid' => false, 'reason' => 'hwid_mismatch', 'payload' => $payload];
        }

        return ['valid' => true, 'reason' => null, 'payload' => $payload];
    }

    /** ذخیرهٔ کلید واردشده توسط مدیر. */
    public static function store(string $key): void
    {
        Setting::set(self::GROUP . '.key', trim($key), self::GROUP);
    }

    /** شناسهٔ سخت‌افزاریِ این سرور — کاربر آن را برای فروشنده می‌فرستد. */
    public static function hardwareId(): string
    {
        return Hardware::id();
    }

    private static function hwidMatches(?string $licensed): bool
    {
        $licensed = trim((string) $licensed);

        // کلیدِ بدون HWID یا '*' روی هر سروری معتبر است (کلید قابل انتقال).
        if ($licensed === '' || $licensed === '*') {
            return true;
        }

        return strcasecmp(Hardware::id(), $licensed) === 0;
    }

    private static function graceEndsAt(): Carbon
    {
        $started = Setting::get(self::GROUP . '.grace_started_at');

        if (blank($started)) {
            // نخستین اجرا بدون لایسنس → مهلت از همین حالا شروع می‌شود.
            $started = now()->toIso8601String();
            Setting::set(self::GROUP . '.grace_started_at', $started, self::GROUP);
        }

        return Carbon::parse($started)->addDays((int) config('license.grace_days', 14));
    }

    private static function b64urlDecode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
