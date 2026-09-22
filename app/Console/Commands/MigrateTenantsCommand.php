<?php

namespace App\Console\Commands;

use App\Services\TenantProvisioner;
use Illuminate\Console\Command;

/**
 * اجرای مهاجرت‌های tenant روی همهٔ کسب‌وکارها.
 *
 * پس از به‌روزرسانیِ برنامه صدا زده می‌شود تا مهاجرت‌های تازه نه‌فقط روی کسب‌وکارِ
 * اصلی (که با migrateِ عادی انجام می‌شود) بلکه روی دیتابیس/پیشوندِ همهٔ کسب‌وکارها
 * هم اعمال شود.
 */
class MigrateTenantsCommand extends Command
{
    protected $signature = 'soorin:migrate-tenants';

    protected $description = 'اجرای مهاجرت‌های tenant روی همهٔ کسب‌وکارها (چند-کسب‌وکاری)';

    public function handle(TenantProvisioner $provisioner): int
    {
        $provisioner->migrateAll();

        $this->info('مهاجرت‌های همهٔ کسب‌وکارها اجرا شد.');

        return self::SUCCESS;
    }
}
