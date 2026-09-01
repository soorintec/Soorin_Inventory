<?php

namespace App\Services;

use App\Support\BackupSettings;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * ریختنِ فایلِ پشتیبان روی یک «پوشهٔ شبکه».
 *
 * دو نوع مقصد پشتیبانی می‌شود:
 *
 *  ۱) مسیرِ SMB مثل «//192.168.1.10/backups/soorin» یا «\\SRV\backups» —
 *     با ابزارِ خطِ‌فرمانِ smbclient و نام‌کاربری/رمز اتصال می‌گیرد. این حالت
 *     نیازی به mount کردنِ اشتراک ندارد و روی لینوکس (سرورِ استقرار) کار می‌کند.
 *     (نصب: apt install smbclient)
 *
 *  ۲) مسیرِ معمولیِ فایل‌سیستم مثل «/mnt/backups» یا «Z:\backups» — اشتراکی که
 *     خودِ سیستم‌عامل mount کرده یا یک پوشهٔ محلی. اینجا فقط کپیِ فایل انجام
 *     می‌شود و نام‌کاربری/رمز لازم نیست.
 *
 * هر متد به‌جای پرتاب استثنا، آرایهٔ ['ok'=>bool,'message'=>string] برمی‌گرداند
 * تا صفحه بتواند پیغامِ خوانا نشان دهد و بکاپِ محلی هرگز به‌خاطرِ خطای شبکه نشکند.
 */
class NetworkBackupService
{
    /** @return array{ok: bool, message: string} */
    public function testSaved(): array
    {
        return $this->test(
            BackupSettings::networkPath(),
            BackupSettings::networkUsername(),
            BackupSettings::networkPassword(),
        );
    }

    /**
     * آزمایشِ دسترسی به مقصد: یک فایلِ کوچکِ آزمایشی می‌نویسد و پاک می‌کند.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(string $path, string $username = '', string $password = ''): array
    {
        $path = trim($path);

        if ($path === '') {
            return ['ok' => false, 'message' => __('backups.net_path_empty')];
        }

        $probe = 'soorin-test-' . Str::random(6) . '.txt';

        if ($this->isSmbPath($path)) {
            return $this->smbTest($path, $username, $password, $probe);
        }

        return $this->localTest($path, $probe);
    }

    /**
     * ریختنِ یک فایلِ پشتیبانِ موجود روی مقصدِ شبکهٔ ذخیره‌شده.
     *
     * @return array{ok: bool, message: string}
     */
    public function push(string $localAbsolutePath, ?string $remoteName = null): array
    {
        if (! is_file($localAbsolutePath)) {
            return ['ok' => false, 'message' => __('backups.net_local_missing')];
        }

        $path = trim(BackupSettings::networkPath());

        if ($path === '') {
            return ['ok' => false, 'message' => __('backups.net_path_empty')];
        }

        return $this->pushToPath(
            $path,
            $localAbsolutePath,
            $remoteName,
            BackupSettings::networkUsername(),
            BackupSettings::networkPassword(),
        );
    }

    /**
     * ریختنِ یک فایل روی مقصدی که صریحاً داده می‌شود (به‌جای تنظیماتِ ذخیره‌شده).
     *
     * @return array{ok: bool, message: string}
     */
    public function pushToPath(string $path, string $localAbsolutePath, ?string $remoteName = null, string $username = '', string $password = ''): array
    {
        if (! is_file($localAbsolutePath)) {
            return ['ok' => false, 'message' => __('backups.net_local_missing')];
        }

        $path = trim($path);

        if ($path === '') {
            return ['ok' => false, 'message' => __('backups.net_path_empty')];
        }

        $remoteName ??= basename($localAbsolutePath);

        if ($this->isSmbPath($path)) {
            return $this->smbPush($path, $username, $password, $localAbsolutePath, $remoteName);
        }

        return $this->localPush($path, $localAbsolutePath, $remoteName);
    }

    // -------------------------------------------------------- فایل‌سیستم/mount

    /** @return array{ok: bool, message: string} */
    private function localTest(string $dir, string $probe): array
    {
        $dir = rtrim($dir, "/\\");

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return ['ok' => false, 'message' => __('backups.net_no_dir', ['path' => $dir])];
        }

        $target = $dir . DIRECTORY_SEPARATOR . $probe;

        if (@file_put_contents($target, 'soorin') === false) {
            return ['ok' => false, 'message' => __('backups.net_not_writable', ['path' => $dir])];
        }

        @unlink($target);

        return ['ok' => true, 'message' => __('backups.test_ok')];
    }

    /** @return array{ok: bool, message: string} */
    private function localPush(string $dir, string $local, string $remoteName): array
    {
        $dir = rtrim($dir, "/\\");

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return ['ok' => false, 'message' => __('backups.net_no_dir', ['path' => $dir])];
        }

        if (! @copy($local, $dir . DIRECTORY_SEPARATOR . $remoteName)) {
            return ['ok' => false, 'message' => __('backups.net_copy_failed', ['path' => $dir])];
        }

        return ['ok' => true, 'message' => __('backups.net_pushed', ['file' => $remoteName])];
    }

    // -------------------------------------------------------------------- SMB

    private function isSmbPath(string $path): bool
    {
        return Str::startsWith($path, ['//', '\\\\', 'smb://']);
    }

    /**
     * جدا کردنِ «//host/share» از «زیرپوشه» در یک مسیرِ SMB.
     *
     * @return array{service: string, dir: string}
     */
    private function parseSmb(string $path): array
    {
        // یکدست‌سازی: بک‌اسلش‌ها به اسلش، حذفِ پیشوندِ smb:
        $norm = str_replace('\\', '/', $path);
        $norm = preg_replace('#^smb:#', '', $norm);
        $norm = ltrim($norm, '/');

        $segments = array_values(array_filter(explode('/', $norm), fn ($s) => $s !== ''));

        $host = $segments[0] ?? '';
        $share = $segments[1] ?? '';
        $dir = implode('/', array_slice($segments, 2));

        return [
            'service' => '//' . $host . '/' . $share,
            'dir'     => $dir,
        ];
    }

    /** @return array{ok: bool, message: string} */
    private function smbTest(string $path, string $username, string $password, string $probe): array
    {
        if (! $this->smbClientAvailable()) {
            return ['ok' => false, 'message' => __('backups.smbclient_missing')];
        }

        $parsed = $this->parseSmb($path);
        $dir = $parsed['dir'];

        // یک فایلِ آزمایشی محلی می‌سازیم، put می‌کنیم و بعد پاک می‌کنیم.
        $tmp = tempnam(sys_get_temp_dir(), 'soorin-smb');
        file_put_contents($tmp, 'soorin');

        $script = ($dir !== '' ? 'cd "' . $dir . '"; ' : '')
            . 'put "' . $tmp . '" "' . $probe . '"; '
            . 'del "' . $probe . '"';

        $result = $this->runSmb($parsed['service'], $username, $password, $script);

        @unlink($tmp);

        return $result['ok']
            ? ['ok' => true, 'message' => __('backups.test_ok')]
            : ['ok' => false, 'message' => __('backups.test_fail', ['error' => $result['error']])];
    }

    /** @return array{ok: bool, message: string} */
    private function smbPush(string $path, string $username, string $password, string $local, string $remoteName): array
    {
        if (! $this->smbClientAvailable()) {
            return ['ok' => false, 'message' => __('backups.smbclient_missing')];
        }

        $parsed = $this->parseSmb($path);
        $dir = $parsed['dir'];

        $script = ($dir !== '' ? 'cd "' . $dir . '"; ' : '')
            . 'put "' . $local . '" "' . $remoteName . '"';

        $result = $this->runSmb($parsed['service'], $username, $password, $script);

        return $result['ok']
            ? ['ok' => true, 'message' => __('backups.net_pushed', ['file' => $remoteName])]
            : ['ok' => false, 'message' => __('backups.test_fail', ['error' => $result['error']])];
    }

    /**
     * اجرای smbclient با «فایلِ احرازِ هویت» تا رمز در فهرستِ پردازه‌ها (ps) دیده نشود.
     *
     * @return array{ok: bool, error: string}
     */
    private function runSmb(string $service, string $username, string $password, string $command): array
    {
        // فایلِ موقتِ حاویِ نام‌کاربری/رمز — امن‌تر از گذاشتنِ رمز روی خطِ فرمان.
        $authFile = tempnam(sys_get_temp_dir(), 'soorin-auth');
        file_put_contents($authFile, "username = {$username}\npassword = {$password}\n");
        @chmod($authFile, 0600);

        try {
            $process = new Process([
                'smbclient', $service,
                '-A', $authFile,
                '-c', $command,
            ]);
            $process->setTimeout(120);
            $process->run();

            if ($process->isSuccessful()) {
                return ['ok' => true, 'error' => ''];
            }

            $error = trim($process->getErrorOutput() . ' ' . $process->getOutput());

            return ['ok' => false, 'error' => Str::limit($error !== '' ? $error : 'exit ' . $process->getExitCode(), 300)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => Str::limit($e->getMessage(), 300)];
        } finally {
            @unlink($authFile);
        }
    }

    private function smbClientAvailable(): bool
    {
        try {
            $process = new Process(['smbclient', '--version']);
            $process->setTimeout(15);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }
}
