<?php

namespace App\Services;

use App\Support\AppVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use ZipArchive;

/**
 * به‌روزرسانی خودِ برنامه از داخل پنل.
 *
 * دو راه: آنلاین از گیت‌هاب (git pull) برای استقرارهایی که با git کلون شده‌اند،
 * و آفلاین با آپلود فایل زیپ نسخهٔ جدید. پیش از هر به‌روزرسانی یک پشتیبان
 * دیتابیس گرفته می‌شود تا راه برگشت باز بماند.
 *
 * ملاک «نسخهٔ جدید»: فایل VERSION در ریشهٔ پروژه (مقایسهٔ semver).
 */
class AppUpdateService
{
    /**
     * وضعیت به‌روزرسانی: نسخهٔ فعلی، نسخهٔ موجود، و اینکه آیا جدیدتری هست.
     *
     * @return array{method: string, current: string, latest: ?string, available: bool, error?: string}
     */
    public function status(): array
    {
        $current = AppVersion::current();

        if (! AppVersion::isGitRepo()) {
            // بدون git فقط به‌روزرسانی آفلاین با زیپ ممکن است
            return ['method' => 'offline', 'current' => $current, 'latest' => null, 'available' => false];
        }

        try {
            $root = base_path();

            // اگر fetch شکست بخورد (مثلاً مخزن private بدون توکن در remote)، نباید
            // بی‌صدا با نسخهٔ کش‌شدهٔ زمان کلون مقایسه کنیم و «به‌روز» بگوییم؛
            // خطا را برمی‌گردانیم تا کاربر علت واقعی را ببیند.
            $fetch = Process::path($root)->timeout(90)->env($this->processEnv())->run('git fetch --quiet');

            if (! $fetch->successful()) {
                return [
                    'method'    => 'git',
                    'current'   => $current,
                    'latest'    => null,
                    'available' => false,
                    'error'     => 'ارتباط با گیت‌هاب ناموفق بود (احتمالاً توکن یا دسترسی remote). '
                        . trim($fetch->errorOutput() ?: $fetch->output()),
                ];
            }

            $env = $this->processEnv();
            $upstream = trim(Process::path($root)->env($env)->run('git rev-parse --abbrev-ref --symbolic-full-name @{u}')->output());

            if ($upstream === '') {
                $upstream = 'origin/main';
            }

            $latest = trim(Process::path($root)->env($env)->run('git show ' . escapeshellarg($upstream . ':VERSION'))->output());

            if ($latest === '') {
                $latest = $current;
            }

            return [
                'method'    => 'git',
                'current'   => $current,
                'latest'    => $latest,
                'available' => version_compare($latest, $current, '>'),
            ];
        } catch (\Throwable $e) {
            return [
                'method'    => 'git',
                'current'   => $current,
                'latest'    => null,
                'available' => false,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /** کلید کشِ نتیجهٔ آخرین بررسی روزانهٔ به‌روزرسانی. */
    public const CACHE_KEY = 'soorin.app_update';

    /** آخرین وضعیتِ کش‌شدهٔ به‌روزرسانی (بدون هیچ محاسبه/شبکه). */
    public function cached(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    /** بررسی واقعی را انجام می‌دهد و نتیجه را در کش می‌گذارد (برای دستور روزانه). */
    public function refreshCache(): array
    {
        $status = $this->status();
        $status['checked_at'] = now()->toIso8601String();

        Cache::forever(self::CACHE_KEY, $status);

        return $status;
    }

    /**
     * نسخهٔ جدیدِ موجود برای نشانِ قرمزِ منو — یا null اگر به‌روز است.
     *
     * از کش می‌خواند تا فوری و بدون معطلیِ شبکه باشد. اگر کش کهنه است (بیش از ۲۰
     * ساعت) یک تازه‌سازی را با defer() زمان‌بندی می‌کند تا «پس از ارسال پاسخ به
     * مرورگر» در پس‌زمینهٔ همین درخواست اجرا شود — پس بدون cron و بدون معطل‌کردنِ
     * صفحه، روزی یک‌بار خودش را به‌روز می‌کند. قفل ساده جلوی اجرای هم‌زمان را می‌گیرد.
     */
    public function availableUpdate(): ?string
    {
        $status = $this->cached();

        $checkedAt = $status['checked_at'] ?? null;
        $stale = blank($checkedAt) || Carbon::parse($checkedAt)->addHours(20)->isPast();

        if ($stale && Cache::add(self::CACHE_KEY . '.refreshing', 1, now()->addMinutes(10))) {
            defer(function (): void {
                try {
                    $this->refreshCache();
                } finally {
                    Cache::forget(self::CACHE_KEY . '.refreshing');
                }
            });
        }

        return ! empty($status['available']) ? ($status['latest'] ?? null) : null;
    }

    /** پس از به‌روزرسانیِ موفق، کش را «به‌روز» علامت می‌زند تا نشان قرمز فوراً برود. */
    private function markUpToDate(string $version): void
    {
        Cache::forever(self::CACHE_KEY, [
            'method'     => AppVersion::isGitRepo() ? 'git' : 'offline',
            'current'    => $version,
            'latest'     => $version,
            'available'  => false,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * به‌روزرسانی از گیت‌هاب: پشتیبان، git pull، نصب وابستگی‌ها، مهاجرت، پاک‌سازی کش.
     *
     * @return array{backup: ?string, version: string}
     */
    public function updateFromGit(): array
    {
        if (! AppVersion::isGitRepo()) {
            throw new RuntimeException('این استقرار یک مخزن گیت نیست؛ از به‌روزرسانی با فایل استفاده کن.');
        }

        $backup = $this->safetyBackup();
        $root = base_path();

        $this->run($root, 'git pull --ff-only', 180);
        $this->run($root, 'composer install --no-dev --optimize-autoloader --no-interaction', 600);

        // مهاجرت و پاک‌سازی در پروسهٔ تازه تا کدِ به‌روزشده اجرا شود
        $this->run($root, 'php artisan migrate --force', 300);
        $this->run($root, 'php artisan optimize:clear', 120);

        $version = AppVersion::current();
        $this->markUpToDate($version);

        return ['backup' => $backup, 'version' => $version];
    }

    /**
     * به‌روزرسانی با فایل زیپ نسخهٔ جدید.
     *
     * @return array{backup: ?string, version: string}
     */
    public function updateFromZip(string $zipPath): array
    {
        $tmp = storage_path('app/update-' . uniqid());

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('باز کردن فایل زیپ ناموفق بود.');
        }

        @mkdir($tmp, 0775, true);
        $zip->extractTo($tmp);
        $zip->close();

        // اگر زیپ داخل یک پوشهٔ تک قرار گرفته، همان را ریشه بگیر
        $entries = array_values(array_diff(scandir($tmp) ?: [], ['.', '..']));
        $source = (count($entries) === 1 && is_dir($tmp . '/' . $entries[0]))
            ? $tmp . '/' . $entries[0]
            : $tmp;

        if (! is_file($source . '/artisan')) {
            $this->rrmdir($tmp);

            throw new RuntimeException('این فایل زیپ، بستهٔ برنامه نیست (فایل artisan پیدا نشد).');
        }

        $backup = $this->safetyBackup();

        // کپی روی فایل‌های فعلی، بدون دست‌زدن به .env، storage، .git و vendor
        $this->copyOver($source, base_path(), ['.env', 'storage', '.git', 'vendor', 'VERSION']);
        // VERSION را جدا کپی کن (تا نسخهٔ جدید ثبت شود)
        if (is_file($source . '/VERSION')) {
            @copy($source . '/VERSION', base_path('VERSION'));
        }

        $this->rrmdir($tmp);

        $root = base_path();
        $this->run($root, 'composer install --no-dev --optimize-autoloader --no-interaction', 600);
        $this->run($root, 'php artisan migrate --force', 300);
        $this->run($root, 'php artisan optimize:clear', 120);

        $version = AppVersion::current();
        $this->markUpToDate($version);

        return ['backup' => $backup, 'version' => $version];
    }

    /**
     * «اتصال به گیت‌هاب» برای نصب‌هایی که با فایل زیپ نصب شده‌اند (بدون .git).
     *
     * داخل همان پوشه یک مخزن گیت می‌سازد و به origin وصلش می‌کند تا از آن پس
     * «به‌روزرسانی از گیت‌هاب» هم ممکن شود. فایل‌های رهگیری‌نشده (‎.env، vendor،
     * storage، public/branding) دست‌نخورده می‌مانند؛ فقط فایل‌های خودِ برنامه با
     * نسخهٔ مخزن هم‌تراز می‌شوند.
     *
     * @return array{version: string, backup: ?string}
     */
    public function linkToGit(string $url): array
    {
        if (AppVersion::isGitRepo()) {
            throw new RuntimeException('این استقرار همین حالا هم مخزن گیت است؛ نیازی به اتصال دوباره نیست.');
        }

        if (! preg_match('#^https?://#i', trim($url))) {
            throw new RuntimeException('آدرس مخزن باید با http:// یا https:// آغاز شود.');
        }

        // چون فایل‌های برنامه با نسخهٔ مخزن هم‌تراز می‌شوند، اول پشتیبان بگیر.
        $backup = $this->safetyBackup();
        $root = base_path();

        $this->run($root, 'git init', 60);
        $this->run($root, 'git remote add origin ' . escapeshellarg(trim($url)), 60);
        $this->run($root, 'git fetch origin --quiet', 240);
        // -f فایل‌های رهگیری‌شده را با نسخهٔ مخزن هماهنگ می‌کند؛ رهگیری‌نشده‌ها می‌مانند.
        $this->run($root, 'git checkout -f -B main origin/main', 180);
        $this->run($root, 'git branch --set-upstream-to=origin/main main', 60);

        return ['version' => AppVersion::current(), 'backup' => $backup];
    }

    private function safetyBackup(): ?string
    {
        try {
            return app(DatabaseBackupService::class)->create('پشتیبان خودکار پیش از به‌روزرسانی برنامه');
        } catch (\Throwable) {
            return null;
        }
    }

    private function run(string $path, string $command, int $timeout): void
    {
        $result = Process::path($path)->timeout($timeout)->env($this->processEnv())->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(
                "دستور ناموفق بود: {$command}\n" . trim($result->errorOutput() ?: $result->output()),
            );
        }
    }

    /**
     * محیطِ اجرای دستورهای بیرونی (git/composer/php).
     *
     * روی دبیان، PHP-FPM با محیطِ پاک‌شده اجرا می‌شود (clear_env=yes پیش‌فرض است)،
     * پس www-data نه HOME دارد نه PATH کاملی. بدون این تنظیم:
     *   - composer پیدا نمی‌شود (چون در /usr/local/bin است، نه در PATH پیش‌فرضِ sh)
     *   - composer با «HOME or COMPOSER_HOME must be set» خطا می‌دهد
     * پس آپدیت از داخل مرورگر شکست می‌خورد. اینجا محیطِ سالمی می‌دهیم.
     *
     * @return array<string, string>
     */
    private function processEnv(): array
    {
        $path = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
        $inherited = getenv('PATH');

        return [
            'HOME'                => storage_path('app'),
            'COMPOSER_HOME'       => storage_path('app/composer'),
            'PATH'                => $inherited ? $path . ':' . $inherited : $path,
            // اگر مخزن private باشد، git به‌جای معطل‌ماندن برای رمز، سریع خطا بدهد.
            'GIT_TERMINAL_PROMPT' => '0',
        ];
    }

    /**
     * کپی بازگشتی محتوای $src روی $dst، با نادیده‌گرفتن نام‌های سطح‌بالای $skip.
     *
     * @param  array<int, string>  $skip
     */
    private function copyOver(string $src, string $dst, array $skip): void
    {
        foreach (array_diff(scandir($src) ?: [], ['.', '..']) as $name) {
            if (in_array($name, $skip, true)) {
                continue;
            }

            $from = $src . '/' . $name;
            $to = $dst . '/' . $name;

            if (is_dir($from)) {
                @mkdir($to, 0775, true);
                $this->copyDir($from, $to);
            } else {
                @copy($from, $to);
            }
        }
    }

    private function copyDir(string $from, string $to): void
    {
        @mkdir($to, 0775, true);

        foreach (array_diff(scandir($from) ?: [], ['.', '..']) as $name) {
            $f = $from . '/' . $name;
            $t = $to . '/' . $name;

            if (is_dir($f)) {
                $this->copyDir($f, $t);
            } else {
                @copy($f, $t);
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
            $path = $dir . '/' . $name;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
