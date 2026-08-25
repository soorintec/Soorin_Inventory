<?php

namespace Database\Seeders;

use App\Enums\Permission as Perm;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ساخت مجوزها و نقش‌ها.
 *
 * نقش‌ها عمداً هیچ مجوزی نمی‌گیرند: مجوز مستقیم روی کاربر می‌نشیند تا مدیر
 * بتواند با برداشتن تیک، دسترسی را از یک کاربر خاص بگیرد. نقش فقط تعیین
 * می‌کند موقع ساخت کاربر کدام تیک‌ها از پیش خورده باشند.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Perm::values() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (array_keys(Perm::defaultsByRole()) as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        User::query()->whereNotNull('user_type')->each(function (User $user) {
            if (! $user->hasRole($user->user_type)) {
                $user->assignRole($user->user_type);
            }

            // کاربری که هنوز هیچ مجوز مستقیمی ندارد، پیش‌فرض نوعش را می‌گیرد
            if ($user->permissions()->count() === 0) {
                $user->syncPermissions(Perm::defaultsByRole()[$user->user_type] ?? []);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
