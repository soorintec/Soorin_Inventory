<?php

use App\Enums\Permission as Perm;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * مجوزِ تازهٔ «مدیریتِ کسب‌وکارها» (businesses.manage) را به مدیرهای موجود می‌دهد.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate(Perm::ManageBusinesses->value, 'web');

        User::withTrashed()
            ->where('user_type', User::TYPE_ADMIN)
            ->get()
            ->each(fn (User $user) => $user->givePermissionTo(Perm::ManageBusinesses->value));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::withTrashed()->get()->each(function (User $user): void {
            if ($user->hasPermissionTo(Perm::ManageBusinesses->value)) {
                $user->revokePermissionTo(Perm::ManageBusinesses->value);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
