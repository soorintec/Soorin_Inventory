<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * ساخت جفت‌کلید Ed25519 برای فروشنده — یک‌بار اجرا می‌شود.
 *
 * کلید عمومی را در config/license.php (یا LICENSE_PUBLIC_KEY در .env) بگذار تا
 * برنامه لایسنس‌ها را تأیید کند. کلید خصوصی را جایی امن نگه دار و هرگز توزیع نکن؛
 * با آن برای هر مشتری کلید می‌سازی (soorin:make-license).
 */
class LicenseKeysCommand extends Command
{
    protected $signature = 'soorin:license-keys {--save : ذخیرهٔ کلید خصوصی در storage/license/private.key}';

    protected $description = 'ساخت جفت‌کلید امضای لایسنس (فروشنده)';

    public function handle(): int
    {
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = bin2hex(sodium_crypto_sign_publickey($keypair));
        $secretKey = bin2hex(sodium_crypto_sign_secretkey($keypair));

        $this->newLine();
        $this->info('کلید عمومی (در config/license.php یا .env → LICENSE_PUBLIC_KEY بگذار):');
        $this->line($publicKey);
        $this->newLine();
        $this->warn('کلید خصوصی (محرمانه! جایی امن نگه دار، هرگز توزیع نکن):');
        $this->line($secretKey);
        $this->newLine();

        if ($this->option('save')) {
            $dir = storage_path('license');
            @mkdir($dir, 0700, true);
            file_put_contents($dir . '/private.key', $secretKey);
            @chmod($dir . '/private.key', 0600);
            $this->info('کلید خصوصی ذخیره شد: storage/license/private.key');
        }

        return self::SUCCESS;
    }
}
