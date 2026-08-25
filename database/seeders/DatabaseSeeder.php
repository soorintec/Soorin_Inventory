<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@yoursite.com'],
            ['name' => 'مدیر انبار', 'mobile' => '09120000001', 'password' => 'password', 'user_type' => User::TYPE_ADMIN],
        );
        $admin->syncRoles(User::TYPE_ADMIN);

        $staff = User::firstOrCreate(
            ['email' => 'karshenas@yoursite.com'],
            ['name' => 'کارشناس انبار', 'mobile' => '09120000002', 'password' => 'password', 'user_type' => User::TYPE_STAFF],
        );
        $staff->syncRoles(User::TYPE_STAFF);

        Warehouse::firstOrCreate(['code' => 'MAIN'], ['name' => 'انبار مرکزی', 'type' => Warehouse::TYPE_MAIN]);
        Warehouse::firstOrCreate(['code' => 'DEF'], ['name' => 'مرجوعی و معیوب', 'type' => Warehouse::TYPE_DEFECTIVE]);

        // دسته و کالای نمونه اینجا ساخته نمی‌شود؛ داده واقعی انبار با دستور
        // inventory:import-anbar از فایل اکسل شرکت وارد می‌شود و کالای نمونه
        // فقط رکورد تکراری و گمراه‌کننده می‌سازد.

        \App\Models\Customer::firstOrCreate(['code' => 'ARIA'], ['name' => 'شرکت آریا', 'city' => 'بوشهر']);

        \App\Models\Supplier::firstOrCreate(['name' => 'تأمین‌کننده شنژن'], ['country' => 'چین']);
        \App\Models\Currency::firstOrCreate(['code' => 'IRR'], ['name' => 'ریال']);
        \App\Models\Currency::firstOrCreate(['code' => 'CNY'], ['name' => 'یوان']);
        \App\Models\Currency::firstOrCreate(['code' => 'USD'], ['name' => 'دلار']);
    }
}
