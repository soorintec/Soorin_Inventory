<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// راه‌اندازی خودکار روی هاست تازه: اگر .env وجود ندارد (اولین اکسترکت پکیج)،
// یکی از روی .env.example با کلید تصادفی و تنظیمات امنِ پیش‌فرض ساخته می‌شود تا
// لاراول بوت شود و ویزارد نصب (/install) بیاید. اطلاعات دیتابیس را خود ویزارد
// می‌نویسد. این کد فقط یک‌بار — وقتی .env نیست — اجرا می‌شود.
(static function (): void {
    $root = dirname(__DIR__);

    if (is_file($root.'/.env') || ! is_file($root.'/.env.example')) {
        return;
    }

    $env = (string) file_get_contents($root.'/.env.example');

    $set = static function (string $key, string $value) use (&$env): void {
        $line = $key.'='.$value;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $env = preg_match($pattern, $env)
            ? preg_replace($pattern, $line, $env)
            : rtrim($env, "\n")."\n".$line."\n";
    };

    $set('APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
    $set('CACHE_STORE', 'file');
    $set('SESSION_DRIVER', 'file');
    $set('QUEUE_CONNECTION', 'sync');
    $set('DB_HOST', '127.0.0.1');

    @file_put_contents($root.'/.env', $env);
})();

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
