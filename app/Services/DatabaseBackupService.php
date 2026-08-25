<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;

/**
 * پشتیبان‌گیری و بازیابی دیتابیس.
 *
 * عمداً از mysqldump استفاده نمی‌کند: روی ویندوز مالک پروژه و روی هاست
 * اشتراکی، اجرای فرمان بیرونی معمولاً در دسترس نیست یا مسیرش فرق دارد.
 * این پیاده‌سازی فقط به خود اتصال دیتابیس نیاز دارد و همه‌جا کار می‌کند.
 *
 * فایل خروجی SQL استاندارد است، پس با phpMyAdmin و mysql هم قابل بازیابی است.
 */
class DatabaseBackupService
{
    /** دیسک و پوشه نگهداری — بیرون از public تا از وب قابل دانلود مستقیم نباشد. */
    private const DISK = 'local';
    private const DIR  = 'backups';

    /** برای جلوگیری از پر شدن دیسک، فقط این تعداد پشتیبان نگه داشته می‌شود. */
    private const KEEP = 20;

    /**
     * ساخت فایل پشتیبان از کل دیتابیس.
     *
     * @return string نام فایل ساخته‌شده
     */
    public function create(?string $reason = null): string
    {
        // پسوند تصادفی لازم است: نام فقط تا ثانیه دقت دارد و بازیابی، پشتیبان
        // ایمنی را در همان ثانیه می‌گیرد. بدون این، پشتیبان ایمنی روی فایلی
        // که داریم از آن بازیابی می‌کنیم می‌نشیند و مبدأ را نابود می‌کند.
        $name = sprintf('backup-%s-%s.sql', Carbon::now()->format('Y-m-d_His'), str()->lower(str()->random(4)));
        $path = $this->absolutePath($name);

        $this->ensureDirectory();

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException('نوشتن فایل پشتیبان ممکن نشد: ' . $path);
        }

        try {
            fwrite($handle, $this->header($reason));
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET NAMES utf8mb4;\n\n");

            foreach ($this->tables() as $table) {
                $this->writeTable($handle, $table);
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }

        $this->pruneOldBackups();

        $this->logQuietly('backup_created', ['file' => $name, 'reason' => $reason]);

        return $name;
    }

    /**
     * بازیابی از یک فایل SQL.
     *
     * پیش از هر کاری یک پشتیبان از وضعیت فعلی گرفته می‌شود — اگر فایل ورودی
     * خراب باشد یا نیمه‌کاره اجرا شود، راه برگشت وجود دارد.
     *
     * @return string|null نام فایل پشتیبانِ پیش از بازیابی، یا null اگر
     *                     دیتابیس خالی بوده و چیزی برای محافظت نبوده است
     */
    public function restore(string $sqlPath): ?string
    {
        if (! is_file($sqlPath)) {
            throw new RuntimeException('فایل پشتیبان پیدا نشد: ' . $sqlPath);
        }

        /*
        | پشتیبان ایمنی فقط وقتی معنی دارد که چیزی برای از دست دادن باشد.
        |
        | مهم‌ترین سناریوی بازیابی همان است که دیتابیس خالی یا خراب است؛ اگر
        | اینجا اصرار می‌کردیم پشتیبان بگیریم، بازیابی روی دیتابیس پاک‌شده
        | اصلاً انجام نمی‌شد — یعنی درست در بدترین لحظه از کار می‌افتاد.
        */
        $safetyCopy = $this->tables() === []
            ? null
            : $this->create('پشتیبان خودکار پیش از بازیابی');

        $pdo = DB::connection()->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $executed = 0;

        try {
            foreach ($this->statements($sqlPath) as $statement) {
                $pdo->exec($statement);
                $executed++;
            }
        } catch (\Throwable $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

            throw new RuntimeException(
                "بازیابی در دستور شماره {$executed} متوقف شد: {$e->getMessage()}"
                . ($safetyCopy
                    ? " — پشتیبان وضعیت پیش از بازیابی در فایل «{$safetyCopy}» موجود است."
                    : ''),
                previous: $e,
            );
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        $this->logQuietly('backup_restored', [
            'source'     => basename($sqlPath),
            'statements' => $executed,
            'safety'     => $safetyCopy,
        ]);

        return $safetyCopy;
    }

    /**
     * ثبت در سیاهه تغییرات، بدون اینکه شکستش کل عملیات را از بین ببرد.
     *
     * بازیابی روی دیتابیسی انجام می‌شود که ممکن است جدول activity_logs
     * نداشته باشد؛ نبودِ یک سطر سیاهه نباید مانع برگرداندن داده شرکت شود.
     */
    private function logQuietly(string $action, array $changes): void
    {
        try {
            ActivityLog::record($action, null, $changes);
        } catch (\Throwable) {
            // دیتابیس هنوز جدول سیاهه را ندارد — عمداً نادیده گرفته می‌شود
        }
    }

    /**
     * فهرست فایل‌های پشتیبان، تازه‌ترین اول.
     *
     * @return array<int, array{name: string, size: int, created_at: Carbon}>
     */
    public function list(): array
    {
        $this->ensureDirectory();

        $files = [];

        foreach (Storage::disk(self::DISK)->files(self::DIR) as $file) {
            if (! str_ends_with($file, '.sql')) {
                continue;
            }

            $files[] = [
                'name'       => basename($file),
                'size'       => Storage::disk(self::DISK)->size($file),
                'created_at' => Carbon::createFromTimestamp(Storage::disk(self::DISK)->lastModified($file)),
            ];
        }

        usort($files, fn (array $a, array $b) => $b['created_at'] <=> $a['created_at']);

        return $files;
    }

    public function delete(string $name): void
    {
        Storage::disk(self::DISK)->delete(self::DIR . '/' . $this->safeName($name));

        ActivityLog::record('backup_deleted', null, ['file' => $name]);
    }

    public function absolutePath(string $name): string
    {
        return Storage::disk(self::DISK)->path(self::DIR . '/' . $this->safeName($name));
    }

    public function exists(string $name): bool
    {
        return Storage::disk(self::DISK)->exists(self::DIR . '/' . $this->safeName($name));
    }

    // ------------------------------------------------------------------ داخلی

    /** نام فایل از ورودی کاربر می‌آید؛ هر چیزی جز نام ساده رد می‌شود. */
    private function safeName(string $name): string
    {
        $name = basename($name);

        if (! preg_match('/^[\w.\-]+\.sql$/', $name)) {
            throw new RuntimeException('نام فایل پشتیبان معتبر نیست.');
        }

        return $name;
    }

    private function ensureDirectory(): void
    {
        if (! Storage::disk(self::DISK)->exists(self::DIR)) {
            Storage::disk(self::DISK)->makeDirectory(self::DIR);
        }
    }

    /** @return array<int, string> */
    private function tables(): array
    {
        $database = DB::connection()->getDatabaseName();

        return array_map(
            fn (object $row) => array_values((array) $row)[0],
            DB::select('SHOW FULL TABLES FROM `' . $database . '` WHERE Table_type = "BASE TABLE"'),
        );
    }

    private function header(?string $reason): string
    {
        return implode("\n", [
            '-- پشتیبان دیتابیس — ' . \App\Support\Branding::appTitle(),
            '-- دیتابیس: ' . DB::connection()->getDatabaseName(),
            '-- تاریخ: ' . Carbon::now()->toDateTimeString(),
            $reason ? '-- علت: ' . $reason : '-- علت: پشتیبان دستی',
            '', '',
        ]);
    }

    /** @param resource $handle */
    private function writeTable($handle, string $table): void
    {
        $create = (array) DB::selectOne('SHOW CREATE TABLE `' . $table . '`');
        $createSql = $create['Create Table'] ?? array_values($create)[1] ?? null;

        fwrite($handle, "-- ساختار جدول {$table}\n");
        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($handle, $createSql . ";\n\n");

        $pdo = DB::connection()->getPdo();
        $columns = null;
        $buffer = [];

        // ردیف‌ها به‌صورت جریانی خوانده می‌شوند تا جدول بزرگ حافظه را پر نکند
        foreach (DB::cursor('SELECT * FROM `' . $table . '`') as $row) {
            $row = (array) $row;
            $columns ??= '`' . implode('`, `', array_keys($row)) . '`';

            $values = array_map(
                fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                array_values($row),
            );

            $buffer[] = '(' . implode(', ', $values) . ')';

            // هر ۱۰۰ ردیف یک INSERT — فایل کوچک‌تر و بازیابی سریع‌تر
            if (count($buffer) >= 100) {
                $this->flushInsert($handle, $table, $columns, $buffer);
            }
        }

        if ($buffer !== []) {
            $this->flushInsert($handle, $table, $columns, $buffer);
        }

        fwrite($handle, "\n");
    }

    /**
     * @param  resource  $handle
     * @param  array<int, string>  $buffer
     */
    private function flushInsert($handle, string $table, string $columns, array &$buffer): void
    {
        fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES\n");
        fwrite($handle, implode(",\n", $buffer) . ";\n");

        $buffer = [];
    }

    /**
     * تقسیم فایل SQL به دستورهای جدا.
     *
     * ساده‌ترین راه (split روی «;») روی داده‌ای که خودش «;» دارد خراب می‌شود،
     * پس وضعیت داخل رشته و کاراکتر فرار دنبال می‌شود.
     *
     * @return \Generator<int, string>
     */
    private function statements(string $path): \Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('خواندن فایل پشتیبان ممکن نشد.');
        }

        $statement = '';
        $quote = null;          // ' یا " وقتی داخل رشته‌ایم
        $escaped = false;

        try {
            while (($chunk = fgets($handle)) !== false) {
                // خط توضیح فقط وقتی نادیده گرفته می‌شود که داخل رشته نباشیم
                if ($quote === null && $statement === '' && preg_match('/^\s*(--|#|\/\*)/', $chunk)) {
                    continue;
                }

                $length = strlen($chunk);

                for ($i = 0; $i < $length; $i++) {
                    $char = $chunk[$i];
                    $statement .= $char;

                    if ($escaped) {
                        $escaped = false;

                        continue;
                    }

                    if ($char === '\\') {
                        $escaped = true;

                        continue;
                    }

                    if ($quote !== null) {
                        if ($char === $quote) {
                            $quote = null;
                        }

                        continue;
                    }

                    if ($char === "'" || $char === '"') {
                        $quote = $char;

                        continue;
                    }

                    if ($char === ';') {
                        $trimmed = trim(substr($statement, 0, -1));

                        if ($trimmed !== '') {
                            yield $trimmed;
                        }

                        $statement = '';
                    }
                }
            }

            $trailing = trim($statement);

            if ($trailing !== '') {
                yield $trailing;
            }
        } finally {
            fclose($handle);
        }
    }

    /** فایل‌های قدیمی‌تر از سقف نگهداری حذف می‌شوند. */
    private function pruneOldBackups(): void
    {
        $files = $this->list();

        foreach (array_slice($files, self::KEEP) as $old) {
            Storage::disk(self::DISK)->delete(self::DIR . '/' . $old['name']);
        }
    }
}
