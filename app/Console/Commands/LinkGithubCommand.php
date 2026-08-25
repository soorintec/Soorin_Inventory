<?php

namespace App\Console\Commands;

use App\Services\AppUpdateService;
use App\Support\AppVersion;
use Illuminate\Console\Command;

/**
 * اتصال یک نصبِ زیپی به گیت‌هاب، از خطِ فرمان.
 *
 * برابرِ ترمینالیِ دکمهٔ «اتصال به گیت‌هاب» در صفحهٔ به‌روزرسانی: داخل پوشهٔ
 * برنامه یک مخزن گیت می‌سازد و به origin وصل می‌کند تا «به‌روزرسانی از گیت‌هاب»
 * ممکن شود. آدرس مخزن پیش‌فرض از config('branding.github.repo') می‌آید.
 *
 * نمونه:
 *     php artisan soorin:link-github
 *     php artisan soorin:link-github --url="https://<TOKEN>@github.com/…/Soorin_Inventory.git"
 */
class LinkGithubCommand extends Command
{
    protected $signature = 'soorin:link-github {--url= : آدرس مخزن گیت‌هاب (پیش‌فرض از config)}';

    protected $description = 'اتصال نصبِ زیپی به گیت‌هاب تا به‌روزرسانی از گیت‌هاب کار کند';

    public function handle(AppUpdateService $service): int
    {
        if (AppVersion::isGitRepo()) {
            $this->warn('این استقرار همین حالا هم مخزن گیت است؛ کاری لازم نیست.');

            return self::SUCCESS;
        }

        $url = $this->option('url') ?: config('branding.github.repo');

        $this->info("اتصال به: {$url}");

        try {
            $result = $service->linkToGit($url);
        } catch (\Throwable $e) {
            $this->error('اتصال ناموفق بود: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("✓ وصل شد. نسخهٔ فعلی: {$result['version']}");
        $this->line('حالا از صفحهٔ «به‌روزرسانی» یا با git pull می‌توانی آپدیت بگیری.');

        return self::SUCCESS;
    }
}
