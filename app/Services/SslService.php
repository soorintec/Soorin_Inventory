<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * مدیریت SSL از داخل پنل.
 *
 * کارهای ریشه‌ای (گرفتن گواهی، ویرایش nginx، reload) را خودِ برنامه نمی‌کند —
 * چون با www-data اجرا می‌شود و نباید root باشد. به‌جایش دستیارِ root به نام
 * `/usr/local/bin/soorin-ssl` را از راه sudo صدا می‌زند که فقط همین دستور برای
 * www-data مجاز شده است. آرگومان‌ها به‌صورت آرایه (نه رشته) پاس می‌شوند تا شل
 * تفسیرشان نکند، و خودِ دستیار هم ورودی را دوباره اعتبارسنجی می‌کند.
 */
class SslService
{
    private const BIN = '/usr/local/bin/soorin-ssl';

    public const GROUP = 'ssl';

    /** آیا دستیار روی سرور نصب است؟ (روی ویندوز/توسعه نصب نیست) */
    public function isHelperInstalled(): bool
    {
        return is_file(self::BIN);
    }

    /**
     * وضعیت فعلی SSL. اگر دستیار نصب نباشد، installed=false برمی‌گردد.
     *
     * @return array<string, string|bool>
     */
    public function status(): array
    {
        if (! $this->isHelperInstalled()) {
            return ['installed' => false];
        }

        $result = $this->run(['status']);

        if (! $result->successful()) {
            return ['installed' => false, 'error' => trim($result->errorOutput() ?: $result->output())];
        }

        return $this->parse($result->output());
    }

    public function issueSelfSigned(string $cn): array
    {
        $this->remember(['mode' => 'self-signed', 'server_name' => $cn]);

        return $this->call(['self-signed', $cn]);
    }

    public function issueLetsEncrypt(string $domain, string $email): array
    {
        $this->remember(['mode' => 'letsencrypt', 'domain' => $domain, 'email' => $email]);

        return $this->call(['letsencrypt', $domain, $email]);
    }

    public function setForceHttps(bool $on): array
    {
        $this->remember(['force' => $on ? '1' : '0']);

        return $this->call(['force-https', $on ? 'on' : 'off']);
    }

    public function disable(): array
    {
        $this->remember(['mode' => 'none', 'force' => '0']);

        return $this->call(['disable']);
    }

    /** اجرای یک دستور تغییردهنده و برگرداندن وضعیت تازه؛ خطا → استثنا. */
    private function call(array $args): array
    {
        if (! $this->isHelperInstalled()) {
            throw new RuntimeException('دستیار SSL روی این سرور نصب نیست. راهنمای نصب در صفحه آمده است.');
        }

        $result = $this->run($args);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'اجرای دستور SSL ناموفق بود.');
        }

        return $this->status();
    }

    private function run(array $args): \Illuminate\Contracts\Process\ProcessResult
    {
        return Process::timeout(180)
            ->env(['PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'])
            ->run(array_merge(['sudo', '-n', self::BIN], $args));
    }

    /** خروجی key=value دستیار را به آرایه تبدیل می‌کند. */
    private function parse(string $output): array
    {
        $data = ['installed' => true];

        foreach (preg_split('/\r?\n/', trim($output)) as $line) {
            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            // installed را خودمان به‌صورت بولی نگه می‌داریم؛ خطِ installed=1 دستیار
            // نباید آن را به رشته تبدیل کند.
            if ($key === 'installed') {
                continue;
            }

            $data[$key] = trim($value);
        }

        return $data;
    }

    /** ذخیرهٔ انتخاب‌ها در settings تا فرم دفعهٔ بعد پیش‌پر شود. */
    private function remember(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::set(self::GROUP . '.' . $key, $value, self::GROUP);
        }
    }

    public function remembered(string $key, mixed $default = null): mixed
    {
        return Setting::get(self::GROUP . '.' . $key, $default);
    }
}
