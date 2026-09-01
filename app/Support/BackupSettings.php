<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * تنظیماتِ «بکاپ روی شبکه» و «زمان‌بندیِ خودکار».
 *
 * همه در جدول settings (گروه backup) ذخیره می‌شوند. رمزِ عبورِ پوشهٔ شبکه
 * رمزنگاری‌شده نگه‌داری می‌شود تا در دیتابیس لو نرود. زمان‌بندی به‌صورت
 * «هر روز / هفتگی / ماهانه در ساعتِ مقرر» تعریف می‌شود و دستورِ
 * soorin:scheduled-backup سرِ وقت آن را اجرا می‌کند.
 */
class BackupSettings
{
    public const GROUP = 'backup';

    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    // ---------------------------------------------------------------- شبکه

    public static function networkEnabled(): bool
    {
        return (bool) Setting::get(self::key('network_enabled'), false);
    }

    public static function networkPath(): string
    {
        return (string) Setting::get(self::key('network_path'), '');
    }

    public static function networkUsername(): string
    {
        return (string) Setting::get(self::key('network_username'), '');
    }

    /** رمز به‌صورت رمزنگاری‌شده ذخیره می‌شود؛ اینجا رمزگشایی می‌شود. */
    public static function networkPassword(): string
    {
        $stored = (string) Setting::get(self::key('network_password'), '');

        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            // اگر کلیدِ برنامه عوض شده باشد، مقدار قابلِ رمزگشایی نیست.
            return '';
        }
    }

    /** آیا مقصدِ شبکه به‌قدرِ کافی پُر شده که بشود بکاپ را آنجا ریخت؟ */
    public static function isNetworkConfigured(): bool
    {
        return self::networkEnabled() && self::networkPath() !== '';
    }

    // -------------------------------------------------------------- زمان‌بندی

    public static function scheduleEnabled(): bool
    {
        return (bool) Setting::get(self::key('schedule_enabled'), false);
    }

    public static function frequency(): string
    {
        $value = (string) Setting::get(self::key('schedule_frequency'), 'daily');

        return in_array($value, self::FREQUENCIES, true) ? $value : 'daily';
    }

    /** ساعتِ اجرا به‌شکل HH:MM (وقتِ سرور). */
    public static function time(): string
    {
        $value = (string) Setting::get(self::key('schedule_time'), '02:00');

        return preg_match('/^\d{1,2}:\d{2}$/', $value) ? $value : '02:00';
    }

    /** روزِ هفته برای حالتِ هفتگی (0=یکشنبه … 6=شنبه، مطابق Carbon). */
    public static function weekday(): int
    {
        return (int) Setting::get(self::key('schedule_weekday'), 6); // پیش‌فرض: شنبه
    }

    /** روزِ ماه برای حالتِ ماهانه (۱ تا ۳۱؛ اگر ماه کوتاه‌تر باشد، آخرین روزِ ماه اجرا می‌شود). */
    public static function monthday(): int
    {
        $day = (int) Setting::get(self::key('schedule_monthday'), 1);

        return max(1, min(31, $day));
    }

    public static function lastRun(): ?Carbon
    {
        $value = Setting::get(self::key('schedule_last_run'));

        return filled($value) ? Carbon::parse($value) : null;
    }

    public static function markRan(?Carbon $at = null): void
    {
        Setting::set(self::key('schedule_last_run'), ($at ?? now())->toIso8601String(), self::GROUP, 'string');
    }

    /**
     * آخرین «ضربانِ» زمان‌بندِ سیستم‌عامل.
     *
     * عمداً بی‌واسطه از دیتابیس خوانده می‌شود (نه از کشِ Setting::get) چون هر
     * دقیقه عوض می‌شود و کشِ همیشگی آن را کهنه نشان می‌دهد.
     */
    public static function schedulerHeartbeat(): ?Carbon
    {
        $value = Setting::where('key', self::key('scheduler_heartbeat'))->value('value');

        return filled($value) ? Carbon::parse($value) : null;
    }

    /** آیا زمان‌بندِ سرور در چند دقیقهٔ اخیر اجرا شده؟ (هر دقیقه ضربان می‌زند) */
    public static function isSchedulerAlive(): bool
    {
        $beat = self::schedulerHeartbeat();

        return $beat !== null && $beat->gt(now()->subMinutes(3));
    }

    // ------------------------------------------------------- خواندن/نوشتنِ فرم

    /**
     * مقادیرِ فعلی برای پُر کردنِ فرمِ تنظیمات.
     * رمز عمداً برنمی‌گردد — فیلدِ رمز خالی می‌ماند و اگر خالی بماند، رمزِ قبلی حفظ می‌شود.
     *
     * @return array<string, mixed>
     */
    public static function formState(): array
    {
        return [
            'network_enabled'   => self::networkEnabled(),
            'network_path'      => self::networkPath(),
            'network_username'  => self::networkUsername(),
            'network_password'  => '',
            'network_on_disable' => 'keep',
            'schedule_enabled'  => self::scheduleEnabled(),
            'schedule_frequency' => self::frequency(),
            'schedule_time'     => self::time(),
            'schedule_weekday'  => self::weekday(),
            'schedule_monthday' => self::monthday(),
        ];
    }

    /**
     * ذخیرهٔ فرمِ تنظیمات. اگر فیلدِ رمز خالی باشد، رمزِ قبلی دست‌نخورده می‌ماند.
     *
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        $networkEnabled = (bool) ($data['network_enabled'] ?? false);
        Setting::set(self::key('network_enabled'), $networkEnabled, self::GROUP, 'bool');

        if ($networkEnabled) {
            // روشن: مقادیرِ فرم نوشته می‌شوند.
            Setting::set(self::key('network_path'), (string) ($data['network_path'] ?? ''), self::GROUP, 'string');
            Setting::set(self::key('network_username'), (string) ($data['network_username'] ?? ''), self::GROUP, 'string');

            // رمز فقط وقتی نوشته می‌شود که کاربر مقدارِ تازه‌ای وارد کرده باشد.
            if (filled($data['network_password'] ?? null)) {
                Setting::set(self::key('network_password'), Crypt::encryptString((string) $data['network_password']), self::GROUP, 'string');
            }
        } elseif (($data['network_on_disable'] ?? 'keep') === 'clear') {
            // خاموش + انتخابِ «پاک کن»: مسیر/یوزر/رمز صفر می‌شوند.
            self::clearNetwork();
        }
        // خاموش + «نگه‌دار» (پیش‌فرض): مسیر/یوزر/رمز دست‌نخورده می‌مانند تا دفعهٔ
        // بعد که روشن شد، لازم نباشد دوباره وارد شوند.

        Setting::set(self::key('schedule_enabled'), (bool) ($data['schedule_enabled'] ?? false), self::GROUP, 'bool');
        Setting::set(self::key('schedule_frequency'), (string) ($data['schedule_frequency'] ?? 'daily'), self::GROUP, 'string');
        Setting::set(self::key('schedule_time'), (string) ($data['schedule_time'] ?? '02:00'), self::GROUP, 'string');
        Setting::set(self::key('schedule_weekday'), (int) ($data['schedule_weekday'] ?? 6), self::GROUP, 'int');
        Setting::set(self::key('schedule_monthday'), (int) ($data['schedule_monthday'] ?? 1), self::GROUP, 'int');
    }

    /** پاک‌کردنِ مسیر/نام‌کاربری/رمزِ پوشهٔ شبکه. */
    public static function clearNetwork(): void
    {
        Setting::set(self::key('network_path'), '', self::GROUP, 'string');
        Setting::set(self::key('network_username'), '', self::GROUP, 'string');
        Setting::set(self::key('network_password'), '', self::GROUP, 'string');
    }

    private static function key(string $suffix): string
    {
        return self::GROUP . '.' . $suffix;
    }
}
