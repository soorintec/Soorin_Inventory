<?php

namespace App\Support;

/**
 * شناسهٔ سخت‌افزاری پایدارِ سرور (Hardware ID).
 *
 * از چند نشانهٔ سرور ساخته می‌شود تا هم پایدار بماند و هم «کپی‌شدنِ» سرور را
 * لو بدهد:
 *   ۱. machine-id سیستم‌عامل (لینوکس /etc/machine-id، ویندوز MachineGuid) — پس از
 *      نصب ثابت است.
 *   ۲. مکِ کارت شبکهٔ اصلی — هنگام «کپیِ» ماشین مجازی (مثلاً پاسخ «I Copied It» در
 *      ESXi یا Clone در vCenter) هایپروایزر مک تازه می‌سازد، پس با کپی عوض می‌شود.
 *
 * چون هر دو با هم هَش می‌شوند، اگر سرور کپی شود و مک تغییر کند، HWID عوض می‌شود و
 * لایسنسِ قفل‌شده به سخت‌افزار دیگر معتبر نمی‌ماند — هر بار که برنامه بالا می‌آید و
 * لایسنس تأیید می‌شود این تطابق سنجیده می‌شود.
 */
class Hardware
{
    /** شناسهٔ نمایشی: گروه‌های چهارتاییِ HEX مثل A1B2-C3D4-… */
    public static function id(): string
    {
        // برای تست‌ها و موارد خاص، امکان بازنویسی از config.
        $override = config('license.hwid_override');
        if (filled($override)) {
            return (string) $override;
        }

        $hash = strtoupper(substr(hash('sha256', 'soorin-hwid|' . self::rawMachineId()), 0, 24));

        return implode('-', str_split($hash, 4));
    }

    private static function rawMachineId(): string
    {
        $parts = [];

        // machine-id سیستم‌عامل — پایدارترین جزء.
        $parts[] = 'mid:' . self::osMachineId();

        // مکِ کارت شبکهٔ اصلی — جزئی که با کپیِ ماشین مجازی عوض می‌شود.
        if ($mac = self::primaryMac()) {
            $parts[] = 'mac:' . $mac;
        }

        return implode('|', $parts);
    }

    /** machine-id سیستم‌عامل (لینوکس/ویندوز) یا نام میزبان به‌عنوان پشتیبان. */
    private static function osMachineId(): string
    {
        // لینوکس (سرور واقعی): پایدارترین منبع.
        foreach (['/etc/machine-id', '/var/lib/dbus/machine-id'] as $file) {
            if (is_file($file)) {
                $value = trim((string) @file_get_contents($file));

                if ($value !== '') {
                    return $value;
                }
            }
        }

        // ویندوز: MachineGuid از رجیستری.
        if (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
            $out = @shell_exec('reg query "HKLM\\SOFTWARE\\Microsoft\\Cryptography" /v MachineGuid 2>NUL');

            if (is_string($out) && preg_match('/MachineGuid\s+REG_SZ\s+([0-9A-Fa-f\-]+)/', $out, $m)) {
                return trim($m[1]);
            }
        }

        // پشتیبان: نام میزبان (کم‌یکتاتر، ولی بهتر از هیچ).
        return (string) (gethostname() ?: 'unknown-host');
    }

    /**
     * مکِ کارت شبکهٔ اصلی. رابط‌های مجازی (loopback، docker، veth، bridge) رد
     * می‌شوند تا شناسه پایدار بماند. اگر پیدا نشد، رشتهٔ خالی برمی‌گرداند.
     */
    private static function primaryMac(): string
    {
        // لینوکس: /sys/class/net قابل خواندن برای www-data است.
        $netDir = '/sys/class/net';

        if (is_dir($netDir)) {
            $candidates = [];

            foreach (@scandir($netDir) ?: [] as $iface) {
                if ($iface === '.' || $iface === '..' || $iface === 'lo') {
                    continue;
                }

                // رابط‌های مجازی/گذرا را نادیده بگیر.
                if (preg_match('/^(docker|veth|br-|virbr|vmnet|tap|tun|bond|dummy)/', $iface)) {
                    continue;
                }

                $addrFile = "$netDir/$iface/address";
                $idxFile  = "$netDir/$iface/ifindex";

                if (! is_readable($addrFile)) {
                    continue;
                }

                $mac = strtolower(trim((string) @file_get_contents($addrFile)));

                // مکِ خالی یا همه‌صفر بی‌ارزش است.
                if ($mac === '' || $mac === '00:00:00:00:00:00') {
                    continue;
                }

                $idx = is_readable($idxFile) ? (int) trim((string) @file_get_contents($idxFile)) : 9999;
                $candidates[$idx] = $mac;
            }

            if ($candidates !== []) {
                ksort($candidates); // کم‌ترین ifindex = رابط اصلی

                return (string) reset($candidates);
            }
        }

        // ویندوز: اولین مکِ فیزیکی از getmac.
        if (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
            $out = @shell_exec('getmac /fo csv /nh 2>NUL');

            if (is_string($out) && preg_match('/([0-9A-Fa-f]{2}(?:-[0-9A-Fa-f]{2}){5})/', $out, $m)) {
                return strtolower(str_replace('-', ':', $m[1]));
            }
        }

        return '';
    }
}
