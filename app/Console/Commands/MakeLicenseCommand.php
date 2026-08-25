<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * ساخت یک کلید لایسنسِ امضاشده برای یک مشتری — فروشنده اجرا می‌کند.
 *
 * نمونه:
 *   php artisan soorin:make-license --to="شرکت آریا" --hwid="A1B2-C3D4-E5F6-1234-5678-9ABC"
 *   php artisan soorin:make-license --to="X" --hwid="..." --expires="2027-01-01"
 *
 * HWID خالی = کلید قابل انتقال (روی هر سروری کار می‌کند).
 * کلید خصوصی از storage/license/private.key یا گزینهٔ --private یا env خوانده می‌شود.
 */
class MakeLicenseCommand extends Command
{
    protected $signature = 'soorin:make-license
                            {--to= : نام مشتری/دارندهٔ لایسنس}
                            {--hwid= : شناسهٔ سخت‌افزاریِ سرورِ مشتری (خالی = قابل انتقال)}
                            {--expires= : تاریخ انقضا YYYY-MM-DD (خالی = دائمی)}
                            {--edition=standard : نسخهٔ محصول}
                            {--private= : مسیر فایل کلید خصوصی}';

    protected $description = 'ساخت کلید لایسنس امضاشده برای یک مشتری';

    public function handle(): int
    {
        $to = $this->option('to') ?: $this->ask('نام مشتری/دارندهٔ لایسنس');

        if (blank($to)) {
            $this->error('نام دارندهٔ لایسنس لازم است.');

            return self::FAILURE;
        }

        $secretHex = $this->secretKey();

        if ($secretHex === null) {
            $this->error('کلید خصوصی پیدا نشد. اول «php artisan soorin:license-keys --save» را بزن یا با --private مسیرش را بده.');

            return self::FAILURE;
        }

        $expires = $this->option('expires');

        if ($expires) {
            try {
                $expires = Carbon::parse($expires)->endOfDay()->toIso8601String();
            } catch (\Throwable) {
                $this->error('تاریخ انقضا نامعتبر است (نمونه: 2027-01-01).');

                return self::FAILURE;
            }
        }

        $payload = [
            'licensed_to' => $to,
            'hwid'        => $this->option('hwid') ?: '',
            'edition'     => $this->option('edition') ?: 'standard',
            'issued_at'   => now()->toIso8601String(),
            'expires_at'  => $expires ?: null,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sig  = sodium_crypto_sign_detached($json, hex2bin($secretHex));

        $key = $this->b64url($json) . '.' . $this->b64url($sig);

        $this->newLine();
        $this->info('کلید لایسنس (برای مشتری بفرست):');
        $this->line($key);
        $this->newLine();
        $this->table(['فیلد', 'مقدار'], [
            ['دارنده', $payload['licensed_to']],
            ['HWID', $payload['hwid'] ?: '(قابل انتقال)'],
            ['انقضا', $payload['expires_at'] ?: '(دائمی)'],
            ['نسخه', $payload['edition']],
        ]);

        return self::SUCCESS;
    }

    private function secretKey(): ?string
    {
        $path = $this->option('private') ?: storage_path('license/private.key');

        if (is_file($path)) {
            $hex = trim((string) file_get_contents($path));

            return ctype_xdigit($hex) ? $hex : null;
        }

        $env = (string) env('LICENSE_PRIVATE_KEY', '');

        return ctype_xdigit($env) && $env !== '' ? $env : null;
    }

    private function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
