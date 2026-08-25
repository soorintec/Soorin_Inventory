<?php

use App\Enums\Permission as Perm;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * انتقال مجوزها از نقش به خودِ کاربر.
     *
     * تا امروز مجوز از راه نقش می‌آمد، پس مدیر نمی‌توانست دسترسی را از یک
     * کاربر خاص بگیرد؛ نقش دوباره آن را برمی‌گرداند. حالا مجوز مستقیم روی
     * کاربر می‌نشیند و برداشتن تیک واقعاً کار می‌کند.
     *
     * هیچ کاربری دسترسی از دست نمی‌دهد: آنچه امروز از نقش داشت، عیناً
     * به‌صورت مستقیم به او داده می‌شود.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Perm::values() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // مجوز قدیمی backups.manage به چهار مجوز ریزتر شکسته شد
        $legacyBackup = Permission::where('name', 'backups.manage')->first();

        foreach (User::withTrashed()->with('roles.permissions')->get() as $user) {
            $fromRoles = $user->roles
                ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
                ->all();

            $direct = $user->permissions->pluck('name')->all();

            $effective = array_unique(array_merge($fromRoles, $direct));

            // هر کس backups.manage داشت، هر چهار مجوز تازه را می‌گیرد
            if (in_array('backups.manage', $effective, true)) {
                $effective = array_merge($effective, [
                    Perm::ViewBackups->value, Perm::CreateBackups->value,
                    Perm::DeleteBackups->value, Perm::RestoreBackups->value,
                ]);
            }

            // مجوزهای تازه‌ای که هنگام ساخت این کاربر وجود نداشتند
            if ($user->user_type === User::TYPE_ADMIN) {
                $effective = Perm::values();
            } elseif (in_array(Perm::ManageStock->value, $effective, true)) {
                $effective[] = Perm::ManageStocktakes->value;
            }

            $effective = array_values(array_intersect(array_unique($effective), Perm::values()));

            $user->syncPermissions($effective);
        }

        // نقش‌ها دیگر مجوزی نمی‌دهند؛ فقط برچسب نوع حساب می‌مانند
        foreach (Role::all() as $role) {
            $role->syncPermissions([]);
        }

        $legacyBackup?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Perm::defaultsByRole() as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }

        User::withTrashed()->get()->each(fn (User $user) => $user->syncPermissions([]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
