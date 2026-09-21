<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Currency;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * ساختِ زیرساختِ یک کسب‌وکارِ تازه (tenant): دیتابیس/پیشوند، جدول‌ها، و دادهٔ پایه.
 *
 * فقط مهاجرت‌های tenant (پوشهٔ database/migrations/tenant) روی هدفِ کسب‌وکار اجرا
 * می‌شوند — نه مهاجرت‌های مرکزی. سپس انبارهای پیش‌فرض (MAIN/DEF) و ارزها seed
 * می‌شوند (ترتیب مهم: MAIN باید پیش از هر ورژنِ کالا باشد).
 */
class TenantProvisioner
{
    /**
     * ساختِ یک کسب‌وکارِ تازه و آماده‌سازیِ کاملش.
     *
     * @param  User|null  $owner  کاربری که دسترسی به کسب‌وکارِ تازه بگیرد (سازنده).
     */
    public function create(string $name, ?string $code, ?User $owner = null): Business
    {
        $business = new Business([
            'name'      => $name,
            'code'      => $code,
            'is_active' => true,
        ]);

        // هدفِ tenant بر اساسِ حالتِ نصب. برای یکتایی از id استفاده می‌کنیم، پس اول
        // رکورد را می‌سازیم تا id بگیریم، بعد پیشوند/دیتابیس را ست می‌کنیم.
        $business->save();

        if (Tenancy::mode() === 'database') {
            $central = config('database.connections.'.config('database.default').'.database');
            $business->database = $central.'_b'.$business->id;
            $business->table_prefix = null;
        } else {
            $business->table_prefix = 'b'.$business->id.'_';
            $business->database = null;
        }

        $business->save();

        $this->provision($business);

        if ($owner !== null) {
            $owner->businesses()->syncWithoutDetaching([$business->id]);
        }

        return $business;
    }

    /**
     * اجرای مهاجرت‌های tenant + seedِ دادهٔ پایه روی هدفِ این کسب‌وکار.
     */
    public function provision(Business $business): void
    {
        // در حالتِ database اگر دیتابیس نیست، ساخته شود.
        if (Tenancy::mode() === 'database' && filled($business->database)) {
            $charset = config('database.connections.'.config('database.default').'.charset', 'utf8mb4');
            $collation = config('database.connections.'.config('database.default').'.collation', 'utf8mb4_unicode_ci');
            DB::connection(config('database.default'))->statement(
                "CREATE DATABASE IF NOT EXISTS `{$business->database}` CHARACTER SET {$charset} COLLATE {$collation}"
            );
        }

        // اتصالِ tenant را روی این کسب‌وکار هدف‌گیری کن.
        Tenancy::use($business);

        // migrate --database=tenant اتصالِ پیش‌فرض را موقتاً tenant می‌کند تا حتی
        // DB::table داخلِ مهاجرت‌ها هم به tenant برود؛ بعد باید بازگردانده شود.
        $originalDefault = DB::getDefaultConnection();

        try {
            // فقط مهاجرت‌های tenant، روی اتصالِ tenant.
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path'     => 'database/migrations/tenant',
                '--force'    => true,
            ]);

            DB::setDefaultConnection($originalDefault);

            $this->seedBaseData();
        } finally {
            DB::setDefaultConnection($originalDefault);
        }
    }

    /**
     * حذفِ زیرساختِ یک کسب‌وکار — دیتابیسِ آن (حالتِ database) یا همهٔ جدول‌های
     * پیشوندی‌اش (حالتِ prefix). برای پاک‌سازیِ تست و قابلیتِ «حذفِ کسب‌وکار».
     */
    public function drop(Business $business): void
    {
        if (Tenancy::mode() === 'database' && filled($business->database)) {
            DB::connection(config('database.default'))->statement("DROP DATABASE IF EXISTS `{$business->database}`");

            return;
        }

        $prefix = (string) ($business->table_prefix ?? '');

        if ($prefix === '') {
            return; // کسب‌وکارِ اصلی؛ جدول‌های بی‌پیشوند حذف نمی‌شوند.
        }

        $conn = DB::connection(config('database.default'));
        $database = $conn->getDatabaseName();

        $tables = $conn->select(
            'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = ? AND table_name LIKE ?',
            [$database, $prefix.'%'],
        );

        $conn->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $t) {
            $name = $t->name;
            $conn->statement("DROP TABLE IF EXISTS `{$name}`");
        }

        $conn->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /** دادهٔ پایهٔ هر کسب‌وکار: انبارهای MAIN/DEF و ارزها. */
    private function seedBaseData(): void
    {
        Warehouse::firstOrCreate(['code' => 'MAIN'], ['name' => 'انبار مرکزی', 'is_active' => true]);
        Warehouse::firstOrCreate(['code' => 'DEF'], ['name' => 'مرجوعی و معیوب', 'is_active' => true]);

        foreach (['IRR' => 'ریال', 'USD' => 'دلار', 'CNY' => 'یوان'] as $code => $name) {
            Currency::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
