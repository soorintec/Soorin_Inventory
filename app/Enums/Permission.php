<?php

namespace App\Enums;

/**
 * فهرست مجوزهای سامانه.
 *
 * مجوزها **مستقیم روی کاربر** ذخیره می‌شوند، نه از راه نقش. دلیلش این است که
 * مدیر باید بتواند دسترسی را از یک کاربر خاص **بگیرد**؛ اگر مجوز از نقش
 * می‌آمد، برداشتن تیک اثری نداشت چون نقش دوباره آن را می‌داد.
 *
 * نقش (admin/staff) فقط تعیین می‌کند موقع ساخت کاربر کدام تیک‌ها از پیش
 * خورده باشند.
 */
enum Permission: string
{
    // کالا
    case ViewItems   = 'items.view';
    case ManageItems = 'items.manage';

    // انبار و موجودی
    case ViewStock        = 'stock.view';
    case ManageStock      = 'stock.manage';       // ثبت ورود و خروج کالا
    case ManageWarehouses = 'warehouses.manage';
    case ManageStocktakes = 'stocktakes.manage';

    // خرید و واردات
    case ViewPurchases   = 'purchases.view';
    case ManagePurchases = 'purchases.manage';

    // سامانه و پروژه
    case ViewProjects       = 'projects.view';
    case ManageProjects     = 'projects.manage';
    case ManageSystemModels = 'system_models.manage';

    // مشتریان
    case ViewCustomers   = 'customers.view';
    case ManageCustomers = 'customers.manage';

    // کاربران و تنظیمات
    case ViewUsers      = 'users.view';
    case ManageUsers    = 'users.manage';
    case ManageSettings = 'settings.manage';
    case ViewActivity   = 'activity.view';

    // گزارش
    case ViewReports = 'reports.view';

    /*
    | پشتیبان‌گیری عمداً به چهار مجوز جدا شکسته شد: «گرفتن پشتیبان» کار
    | بی‌خطری است ولی «بازیابی» کل داده شرکت را دور می‌ریزد، و این دو نباید
    | با یک تیک واحد داده شوند.
    */
    case ViewBackups    = 'backups.view';
    case CreateBackups  = 'backups.create';
    case DeleteBackups  = 'backups.delete';
    case RestoreBackups = 'backups.restore';
    case ManageBackupSettings = 'backups.settings'; // بکاپ روی شبکه و زمان‌بندیِ خودکار

    public function label(): string
    {
        $labels = __('permissions.labels');

        return is_array($labels) ? ($labels[$this->value] ?? $this->value) : $this->value;
    }

    /** توضیح کوتاه برای مجوزهایی که اثرشان بدیهی نیست. */
    public function hint(): ?string
    {
        $hints = __('permissions.hints');

        return is_array($hints) ? ($hints[$this->value] ?? null) : null;
    }

    /**
     * شناسه گروه — لاتین است چون در مسیر state فرم استفاده می‌شود و کلید
     * فارسی آنجا دردسر می‌سازد.
     */
    public function group(): string
    {
        return match ($this) {
            self::ViewItems, self::ManageItems,
            self::ViewStock, self::ManageStock,
            self::ManageWarehouses, self::ManageStocktakes => 'warehouse',

            self::ViewPurchases, self::ManagePurchases => 'purchasing',

            self::ViewProjects, self::ManageProjects,
            self::ManageSystemModels => 'projects',

            self::ViewCustomers, self::ManageCustomers => 'customers',

            self::ViewReports, self::ViewActivity => 'reports',

            self::ViewBackups, self::CreateBackups,
            self::DeleteBackups, self::RestoreBackups,
            self::ManageBackupSettings => 'backups',

            self::ViewUsers, self::ManageUsers, self::ManageSettings => 'system',
        };
    }

    /** برچسب گروه‌ها به زبان فعال. */
    public static function groupLabels(): array
    {
        $groups = __('permissions.groups');

        // اگر فایل زبان نبود، به شناسه‌های لاتین برگرد تا ساختار فرم نشکند.
        return is_array($groups) ? $groups : array_combine(
            ['warehouse', 'purchasing', 'projects', 'customers', 'reports', 'backups', 'system'],
            ['warehouse', 'purchasing', 'projects', 'customers', 'reports', 'backups', 'system'],
        );
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * تیک‌های پیش‌فرض هنگام ساخت کاربر تازه.
     *
     * این فقط نقطه شروع است؛ مدیر می‌تواند هر تیکی را بزند یا بردارد.
     *
     * @return array<string, array<string>>
     */
    public static function defaultsByRole(): array
    {
        $staff = [
            self::ViewItems, self::ViewStock, self::ManageStock,
            self::ViewPurchases, self::ManagePurchases,
            self::ViewProjects, self::ManageProjects,
            self::ViewCustomers, self::ViewReports,
        ];

        return [
            'admin' => self::values(),
            'staff' => array_map(fn (self $c) => $c->value, $staff),
        ];
    }

    /**
     * گزینه‌های فرم، گروه‌بندی‌شده بر اساس شناسه گروه.
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $case) {
            $groups[$case->group()][$case->value] = $case->label();
        }

        return $groups;
    }

    /**
     * پخش کردن یک فهرست تخت مجوز بین گروه‌ها — برای پر کردن فرم.
     *
     * @param  array<int, string>  $permissions
     * @return array<string, array<int, string>>
     */
    public static function splitIntoGroups(array $permissions): array
    {
        $split = array_fill_keys(array_keys(self::groupLabels()), []);

        foreach ($permissions as $permission) {
            $case = self::tryFrom($permission);

            if ($case !== null) {
                $split[$case->group()][] = $case->value;
            }
        }

        return $split;
    }

    /**
     * جمع کردن گروه‌های فرم به یک فهرست تخت — برای ذخیره.
     *
     * @param  array<string, mixed>  $groups
     * @return array<int, string>
     */
    public static function mergeGroups(array $groups): array
    {
        $flat = [];

        foreach ($groups as $values) {
            foreach ((array) $values as $value) {
                if (self::tryFrom((string) $value) !== null) {
                    $flat[] = (string) $value;
                }
            }
        }

        return array_values(array_unique($flat));
    }
}
