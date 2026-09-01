<?php

use App\Enums\Permission as Perm;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * مجوزِ تازهٔ «تنظیماتِ بکاپِ خودکار و شبکه» (backups.settings) را به مدیرهای
     * موجود می‌دهد.
     *
     * چرا مهاجرت لازم است: سیدر فقط به کاربری که هیچ مجوزِ مستقیمی ندارد
     * پیش‌فرض می‌دهد؛ مدیرهایی که از قبل مجوز دارند، این مجوزِ تازه را
     * خودکار نمی‌گیرند. این مهاجرت آن را افزوده می‌کند (بدون دست‌زدن به بقیهٔ
     * تیک‌ها). کارکنان آن را نمی‌گیرند؛ مدیر می‌تواند بعداً در فرمِ کاربر به
     * هرکس خواست بدهد.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate(Perm::ManageBackupSettings->value, 'web');

        User::withTrashed()
            ->where('user_type', User::TYPE_ADMIN)
            ->get()
            ->each(fn (User $user) => $user->givePermissionTo(Perm::ManageBackupSettings->value));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::withTrashed()->get()->each(function (User $user): void {
            if ($user->hasPermissionTo(Perm::ManageBackupSettings->value)) {
                $user->revokePermissionTo(Perm::ManageBackupSettings->value);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
