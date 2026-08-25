<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * راه‌اندازی اولیه سامانه روی یک هاست تازه.
 *
 * این دستور همه‌چیزِ لازم برای اولین اجرا را می‌سازد و idempotent است — اگر
 * دوباره اجرا شود چیزی خراب یا تکراری نمی‌شود:
 *   ۱. مجوزها و نقش‌ها (RolePermissionSeeder)
 *   ۲. انبار پیش‌فرض (مرکزی و مرجوعی) و ارزهای خرید (دلار/یوان)
 *   ۳. یک کاربر مدیر برای اولین ورود
 *
 * برخلاف `db:seed` هیچ داده نمونه‌ای (مشتری/کالای آزمایشی) نمی‌سازد؛ برای
 * محیط واقعی مناسب است.
 */
class InstallCommand extends Command
{
    protected $signature = 'soorin:install
                            {--name= : نام کاربر مدیر}
                            {--email= : ایمیل ورود مدیر}
                            {--mobile= : موبایل مدیر (اختیاری)}
                            {--password= : گذرواژه مدیر}';

    protected $description = 'راه‌اندازی اولیه سامانه: مجوزها، انبار پیش‌فرض و کاربر مدیر';

    public function handle(): int
    {
        $this->info('راه‌اندازی اولیه سامانه انبارداری سورین…');

        // ۱) مجوزها و نقش‌ها
        $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        // ۲) انبار و ارز پیش‌فرض
        Warehouse::firstOrCreate(['code' => 'MAIN'], ['name' => 'انبار مرکزی', 'type' => Warehouse::TYPE_MAIN]);
        Warehouse::firstOrCreate(['code' => 'DEF'], ['name' => 'مرجوعی و معیوب', 'type' => Warehouse::TYPE_DEFECTIVE]);
        Currency::firstOrCreate(['code' => 'IRR'], ['name' => 'ریال']);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'دلار']);
        Currency::firstOrCreate(['code' => 'CNY'], ['name' => 'یوان']);
        $this->line('✓ انبار پیش‌فرض و ارزها آماده شد.');

        // ۳) کاربر مدیر
        $this->createAdmin();

        $this->newLine();
        $this->info('راه‌اندازی کامل شد. حالا می‌توانی وارد پنل شوی: /admin');

        return self::SUCCESS;
    }

    private function createAdmin(): void
    {
        // فیلدهای ضروری فقط وقتی پرسیده می‌شوند که در گزینه‌ها نیامده باشند
        // (در اجرای خودکار/تست همه از گزینه می‌آیند، پس چیزی پرسیده نمی‌شود).
        // موبایل اختیاری است و اصلاً پرسیده نمی‌شود؛ اگر خواستی با --mobile بده.
        $name     = $this->option('name') ?: $this->ask('نام کاربر مدیر', 'مدیر سامانه');
        $email    = $this->option('email') ?: $this->ask('ایمیل ورود مدیر');
        $mobile   = $this->option('mobile') ?: null;
        $password = $this->option('password') ?: $this->secret('گذرواژه مدیر (حداقل ۸ کاراکتر)');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name'     => ['required', 'string', 'max:255'],
                'email'    => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            $this->warn('کاربر مدیر ساخته نشد. دستور را دوباره با اطلاعات درست اجرا کن.');

            return;
        }

        $existing = User::where('email', $email)->first();

        if ($existing) {
            // مدیر با همین ایمیل هست: فقط گذرواژه‌اش به‌روز می‌شود (بازیابی دسترسی)
            $existing->update(['password' => $password, 'is_active' => true, 'user_type' => User::TYPE_ADMIN]);
            $this->line("✓ کاربر «{$email}» از قبل بود؛ گذرواژه‌اش به‌روز شد.");

            return;
        }

        User::create([
            'name'      => $name,
            'email'     => $email,
            'mobile'    => $mobile ?: null,
            'password'  => $password,
            'user_type' => User::TYPE_ADMIN,
            'is_active' => true,
        ]);

        // هوک مدل کاربر خودش نقش و همه مجوزهای مدیر را می‌دهد.
        $this->line("✓ کاربر مدیر «{$email}» ساخته شد.");
    }
}
