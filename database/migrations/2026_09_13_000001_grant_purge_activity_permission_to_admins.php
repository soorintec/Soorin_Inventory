<?php

use App\Enums\Permission as Perm;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * مجوزِ تازهٔ «پاک‌سازیِ سیاههٔ تغییرات» (activity.purge) را به مدیرهای موجود می‌دهد.
     *
     * چرا مهاجرت لازم است: سیدر فقط به کاربری که هیچ مجوزِ مستقیمی ندارد پیش‌فرض
     * می‌دهد؛ مدیرهایی که از قبل مجوز دارند این مجوزِ تازه را خودکار نمی‌گیرند.
     * کارکنان آن را نمی‌گیرند؛ مدیر می‌تواند بعداً در فرمِ کاربر به هرکس خواست بدهد.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate(Perm::PurgeActivityLogs->value, 'web');

        User::withTrashed()
            ->where('user_type', User::TYPE_ADMIN)
            ->get()
            ->each(fn (User $user) => $user->givePermissionTo(Perm::PurgeActivityLogs->value));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::withTrashed()->get()->each(function (User $user): void {
            if ($user->hasPermissionTo(Perm::PurgeActivityLogs->value)) {
                $user->revokePermissionTo(Perm::PurgeActivityLogs->value);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
