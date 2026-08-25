<?php

namespace App\Http\Controllers;

use App\Support\Installation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;

/**
 * ویزارد نصب وب — مثل نصب وردپرس.
 *
 * اطلاعات دیتابیس و کاربر مدیر را می‌گیرد، اتصال را تست می‌کند، در .env
 * می‌نویسد، جدول‌ها را می‌سازد و راه‌اندازی اولیه را انجام می‌دهد، و در پایان
 * قفل نصب را می‌گذارد تا ویزارد دیگر باز نشود.
 */
class InstallController extends Controller
{
    public function show(Request $request)
    {
        if (Installation::isInstalled()) {
            return redirect('/admin');
        }

        return view('install', [
            'guessedUrl' => $request->getSchemeAndHttpHost(),
        ]);
    }

    public function store(Request $request)
    {
        if (Installation::isInstalled()) {
            return redirect('/admin');
        }

        $data = $request->validate([
            'db_host'        => ['required', 'string'],
            'db_port'        => ['required', 'string'],
            'db_database'    => ['required', 'string'],
            'db_username'    => ['required', 'string'],
            'db_password'    => ['nullable', 'string'],
            'app_url'        => ['nullable', 'string'],
            'admin_name'     => ['required', 'string', 'max:255'],
            'admin_email'    => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        // ۱) تست اتصال به دیتابیس با اطلاعات واردشده
        try {
            $dsn = "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_database']}";
            new PDO($dsn, $data['db_username'], $data['db_password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $e) {
            return back()->withInput()->with('installError', 'اتصال به دیتابیس ناموفق بود: ' . $e->getMessage());
        }

        // ۲) نوشتن اطلاعات در .env
        $appUrl = $data['app_url'] ?: $request->getSchemeAndHttpHost();
        $this->writeEnv([
            'APP_URL'          => $appUrl,
            'APP_ENV'          => 'production',
            'APP_DEBUG'        => 'false',
            'DB_CONNECTION'    => 'mysql',
            'DB_HOST'          => $data['db_host'],
            'DB_PORT'          => $data['db_port'],
            'DB_DATABASE'      => $data['db_database'],
            'DB_USERNAME'      => $data['db_username'],
            'DB_PASSWORD'      => $data['db_password'] ?? '',
            'CACHE_STORE'      => 'file',
            'SESSION_DRIVER'   => 'file',
            'QUEUE_CONNECTION' => 'sync',
        ]);

        // ۳) اعمال اطلاعات جدید روی اتصال جاری تا migrate با آن‌ها کار کند
        config([
            'database.connections.mysql.host'     => $data['db_host'],
            'database.connections.mysql.port'     => $data['db_port'],
            'database.connections.mysql.database' => $data['db_database'],
            'database.connections.mysql.username' => $data['db_username'],
            'database.connections.mysql.password' => $data['db_password'] ?? '',
        ]);
        DB::purge('mysql');

        // ۴) ساخت جدول‌ها و راه‌اندازی اولیه (مجوز، انبار، کاربر مدیر)
        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('soorin:install', [
                '--name'     => $data['admin_name'],
                '--email'    => $data['admin_email'],
                '--password' => $data['admin_password'],
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('installError', 'خطا هنگام راه‌اندازی: ' . $e->getMessage());
        }

        // ۵) قفل نصب — از این به بعد ویزارد باز نمی‌شود
        Installation::markInstalled();

        // پاک‌سازی کش تنظیمات تا .env تازه خوانده شود
        Artisan::call('config:clear');

        return redirect('/admin')->with('justInstalled', true);
    }

    /**
     * نوشتن چند کلید در فایل .env (جایگزینی یا افزودن).
     *
     * @param  array<string, string>  $values
     */
    private function writeEnv(array $values): void
    {
        $path = base_path('.env');
        $env = is_file($path) ? file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            // دفاع در عمق: خط جدید در مقدار .env هرگز مجاز نیست — وگرنه می‌شد یک
            // مقدارِ آلوده، خطِ تنظیمِ تازه‌ای به .env تزریق کند (مثلاً APP_DEBUG=true).
            $value = str_replace(["\r", "\n"], '', $value);

            // مقدارهایی که کاراکتر خاص دارند داخل نقل‌قول دوتایی می‌روند
            $needsQuotes = $value === '' || preg_match('/[\s#"\'=]/', $value);
            $written = $needsQuotes ? '"' . str_replace('"', '\"', $value) . '"' : $value;
            $line = $key . '=' . $written;

            if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $env)) {
                $env = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $env);
            } else {
                $env = rtrim($env, "\n") . "\n" . $line . "\n";
            }
        }

        file_put_contents($path, $env);
    }
}
