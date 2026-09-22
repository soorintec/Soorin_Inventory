<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Business;
use App\Support\Tenancy;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * پشتیبان‌گیری و بازیابی دیتابیس (چند-کسب‌وکاری).
 *
 * عمداً از mysqldump استفاده نمی‌کند: روی ویندوز و هاست اشتراکی معمولاً در دسترس
 * نیست. فقط به خودِ اتصال نیاز دارد و خروجی‌اش SQL استاندارد است.
 *
 * دو نوع بکاپ:
 *   - کامل (business = null): جدول‌های مرکزی + جدول‌های همهٔ کسب‌وکارها، یک فایل.
 *   - یک کسب‌وکار (business داده‌شده): فقط جدول‌های همان کسب‌وکار.
 * در حالتِ database (دیتابیسِ جدا برای هر کسب‌وکار)، بخش‌ها با «USE `db`;» مسیردهی
 * می‌شوند تا بازیابی هر بخش را به دیتابیسِ درستش برساند.
 */
class DatabaseBackupService
{
    private const DISK = 'local';
    private const DIR  = 'backups';
    private const KEEP = 20;

    /** جدول‌های دیتای عملیاتیِ هر کسب‌وکار (tenant) — پایه، بدونِ پیشوند. */
    private const TENANT_TABLES = [
        'item_categories', 'items', 'item_versions', 'item_serials',
        'warehouses', 'stock_balances', 'stock_lots', 'stock_movements',
        'stocktakes', 'stocktake_lines',
        'customers', 'customer_systems', 'customer_system_parts',
        'suppliers', 'purchases', 'purchase_items',
        'system_models', 'system_versions', 'system_bom_lines',
        'projects', 'project_checklist_lines',
        'currencies', 'activity_logs',
    ];

    /**
     * ساخت فایل پشتیبان.
     *
     * @param  string|null   $prefix    پیشوندِ نامِ فایل (منبع)؛ null یعنی از کاربرِ واردشده.
     * @param  Business|null $business  اگر داده شود، فقط همان کسب‌وکار؛ وگرنه بکاپِ کامل.
     * @return string نام فایل ساخته‌شده
     */
    public function create(?string $reason = null, ?string $prefix = null, ?Business $business = null): string
    {
        $prefix = $this->sanitizePrefix($prefix ?? $this->currentUserPrefix());

        $name = sprintf('%s_%s_%s.sql', $prefix, Carbon::now()->format('Y-m-d_His'), str()->lower(str()->random(4)));
        $path = $this->absolutePath($name);

        $this->ensureDirectory();

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException('نوشتن فایل پشتیبان ممکن نشد: ' . $path);
        }

        // چون در حینِ بکاپ اتصالِ tenant بینِ کسب‌وکارها جابه‌جا می‌شود، کسب‌وکارِ
        // فعالِ درخواست را نگه می‌داریم و در پایان برمی‌گردانیم.
        $original = Tenancy::active();

        try {
            fwrite($handle, $this->header($reason, $business));
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET NAMES utf8mb4;\n\n");

            $this->writeDump($handle, $business);

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);

            if ($original !== null) {
                Tenancy::use($original);
            }
        }

        $this->pruneOldBackups();

        $this->logQuietly('backup_created', ['file' => $name, 'reason' => $reason, 'business' => $business?->id]);

        return $name;
    }

    /**
     * بازیابی از یک فایل SQL.
     *
     * @param  Business|null $business  اگر داده شود، روی اتصالِ همان کسب‌وکار بازیابی
     *                                  می‌شود؛ وگرنه روی اتصالِ مرکزی (بکاپِ کامل).
     * @return string|null نام فایلِ پشتیبانِ ایمنیِ پیش از بازیابی (یا null).
     */
    public function restore(string $sqlPath, ?Business $business = null): ?string
    {
        if (! is_file($sqlPath)) {
            throw new RuntimeException('فایل پشتیبان پیدا نشد: ' . $sqlPath);
        }

        $original = Tenancy::active();

        // پشتیبانِ ایمنی فقط وقتی چیزی برای از دست دادن باشد.
        $safetyCopy = $this->targetHasData($business)
            ? $this->create('پشتیبان خودکار پیش از بازیابی', 'PreRe', $business)
            : null;

        $connection = $business !== null ? $this->businessConnection($business) : DB::connection();
        $pdo = $connection->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $executed = 0;

        try {
            foreach ($this->statements($sqlPath) as $statement) {
                $pdo->exec($statement);
                $executed++;
            }
        } catch (\Throwable $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

            if ($original !== null) {
                Tenancy::use($original);
            }

            throw new RuntimeException(
                "بازیابی در دستور شماره {$executed} متوقف شد: {$e->getMessage()}"
                . ($safetyCopy ? " — پشتیبان وضعیت پیش از بازیابی در فایل «{$safetyCopy}» موجود است." : ''),
                previous: $e,
            );
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        if ($original !== null) {
            Tenancy::use($original);
        }

        $this->logQuietly('backup_restored', [
            'source'     => basename($sqlPath),
            'statements' => $executed,
            'safety'     => $safetyCopy,
            'business'   => $business?->id,
        ]);

        return $safetyCopy;
    }

    // ---------------------------------------------------------------- بخش‌ها

    /**
     * نوشتنِ درجای دامپ. هر کسب‌وکار درجا (پس از سوییچِ اتصال) dump می‌شود تا
     * اتصالِ tenant که بینِ کسب‌وکارها repoint می‌شود، بخش‌های قبلی را بی‌اعتبار نکند.
     *
     * @param  resource  $handle
     */
    private function writeDump($handle, ?Business $business): void
    {
        if ($business !== null) {
            // فقط همین کسب‌وکار؛ USE فقط اگر خودش دیتابیسِ جدا داشته باشد.
            $this->dumpBusiness($handle, $business, filled($business->database));

            return;
        }

        // کامل: USE لازم است اگر «هر» کسب‌وکاری دیتابیسِ جدا داشته باشد (حالتِ ترکیبی
        // هم ممکن است، چون کاربر می‌تواند حالت را از UI عوض کند). داده‌محور، نه بر
        // اساسِ حالتِ سراسری — تا مسیردهیِ بخش‌ها همیشه درست باشد.
        $useDb = Business::query()->whereNotNull('database')->exists();

        // کامل: بخشِ مرکزی (جدول‌های مرکزی، بدونِ جدول‌های tenant) + هر کسب‌وکار.
        $central = DB::connection();
        $tenantPhysicalAll = $this->allTenantPhysicalTables();
        $centralTables = array_values(array_filter(
            $this->baseTablesOf($central),
            fn (string $t) => ! in_array($t, $tenantPhysicalAll, true),
        ));

        if ($useDb) {
            fwrite($handle, "USE `{$central->getDatabaseName()}`;\n\n");
        }

        foreach ($centralTables as $table) {
            $this->writeTable($handle, $central, $table);
        }

        foreach (Business::query()->orderBy('id')->get() as $b) {
            $this->dumpBusiness($handle, $b, $useDb);
        }
    }

    /** @param resource $handle */
    private function dumpBusiness($handle, Business $business, bool $useDb): void
    {
        $conn = $this->businessConnection($business);

        if ($useDb) {
            fwrite($handle, "USE `{$conn->getDatabaseName()}`;\n\n");
        }

        foreach ($this->businessTables($conn) as $table) {
            $this->writeTable($handle, $conn, $table);
        }
    }

    /** نام‌های فیزیکیِ جدول‌های tenantِ موجود روی این اتصال. */
    private function businessTables(Connection $conn): array
    {
        $prefix = $conn->getTablePrefix();
        $existing = $this->baseTablesOf($conn);

        $tables = [];
        foreach (self::TENANT_TABLES as $base) {
            $physical = $prefix . $base;
            if (in_array($physical, $existing, true)) {
                $tables[] = $physical;
            }
        }

        return $tables;
    }

    /** اتصالِ آمادهٔ یک کسب‌وکار (اتصالِ tenant که رویش سوار شده). */
    private function businessConnection(Business $business): Connection
    {
        Tenancy::use($business);

        return DB::connection('tenant');
    }

    /** همهٔ نام‌های فیزیکیِ جدول‌های tenant در همهٔ کسب‌وکارها (برای کنارگذاشتن از بخشِ مرکزی). */
    private function allTenantPhysicalTables(): array
    {
        $all = [];

        foreach (Business::query()->get() as $b) {
            $prefix = (string) ($b->table_prefix ?? '');

            foreach (self::TENANT_TABLES as $base) {
                $all[] = $prefix . $base;
            }
        }

        return $all;
    }

    /**
     * آیا هدفِ بازیابی چیزی برای پشتیبانِ ایمنی دارد؟
     *
     * مهم: اگر دیتابیس خالی/wipe شده باشد (هیچ جدولی نیست)، پشتیبانِ ایمنی نباید
     * گرفته شود — چون create() برای هدر به جدولِ settings سر می‌زند و روی دیتابیسِ
     * خالی خطا می‌دهد. این همان محافظِ نسخهٔ قبلی است.
     */
    private function targetHasData(?Business $business): bool
    {
        if ($business === null) {
            return $this->baseTablesOf(DB::connection()) !== [];
        }

        return $this->businessTables($this->businessConnection($business)) !== [];
    }

    // ---------------------------------------------------------------- عمومی

    private function logQuietly(string $action, array $changes): void
    {
        try {
            ActivityLog::record($action, null, $changes);
        } catch (\Throwable) {
            // دیتابیس هنوز جدول سیاهه را ندارد — عمداً نادیده گرفته می‌شود
        }
    }

    /**
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

    private function safeName(string $name): string
    {
        $name = basename($name);

        if (! preg_match('/^[\w.\-]+\.sql$/', $name)) {
            throw new RuntimeException('نام فایل پشتیبان معتبر نیست.');
        }

        return $name;
    }

    private function currentUserPrefix(): string
    {
        $user = auth()->user();

        if (! $user) {
            return 'User';
        }

        $candidates = [
            (string) $user->name,
            (string) str((string) $user->email)->before('@'),
        ];

        foreach ($candidates as $candidate) {
            $clean = preg_replace('/[^A-Za-z0-9]/', '', $candidate) ?? '';

            if ($clean !== '') {
                return substr($clean, 0, 5);
            }
        }

        return 'User';
    }

    private function sanitizePrefix(string $prefix): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $prefix) ?? '';

        return $clean !== '' ? substr($clean, 0, 12) : 'Backup';
    }

    private function ensureDirectory(): void
    {
        if (! Storage::disk(self::DISK)->exists(self::DIR)) {
            Storage::disk(self::DISK)->makeDirectory(self::DIR);
        }
    }

    /** نام جدول‌های BASE TABLE یک اتصال. */
    private function baseTablesOf(Connection $conn): array
    {
        $database = $conn->getDatabaseName();

        return array_map(
            fn (object $row) => array_values((array) $row)[0],
            $conn->select('SHOW FULL TABLES FROM `' . $database . '` WHERE Table_type = "BASE TABLE"'),
        );
    }

    private function header(?string $reason, ?Business $business): string
    {
        return implode("\n", [
            '-- پشتیبان دیتابیس — ' . \App\Support\Branding::appTitle(),
            '-- تاریخ: ' . Carbon::now()->toDateTimeString(),
            $business ? '-- کسب‌وکار: ' . $business->name . ' (#' . $business->id . ')' : '-- بکاپِ کامل (همهٔ کسب‌وکارها)',
            $reason ? '-- علت: ' . $reason : '-- علت: پشتیبان دستی',
            '', '',
        ]);
    }

    /** @param resource $handle */
    private function writeTable($handle, Connection $conn, string $table): void
    {
        $create = (array) $conn->selectOne('SHOW CREATE TABLE `' . $table . '`');
        $createSql = $create['Create Table'] ?? array_values($create)[1] ?? null;

        fwrite($handle, "-- ساختار جدول {$table}\n");
        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($handle, $createSql . ";\n\n");

        $pdo = $conn->getPdo();
        $columns = null;
        $buffer = [];

        foreach ($conn->cursor('SELECT * FROM `' . $table . '`') as $row) {
            $row = (array) $row;
            $columns ??= '`' . implode('`, `', array_keys($row)) . '`';

            $values = array_map(
                fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                array_values($row),
            );

            $buffer[] = '(' . implode(', ', $values) . ')';

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
     * @return \Generator<int, string>
     */
    private function statements(string $path): \Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('خواندن فایل پشتیبان ممکن نشد.');
        }

        $statement = '';
        $quote = null;
        $escaped = false;

        try {
            while (($chunk = fgets($handle)) !== false) {
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

    private function pruneOldBackups(): void
    {
        $files = $this->list();

        foreach (array_slice($files, self::KEEP) as $old) {
            Storage::disk(self::DISK)->delete(self::DIR . '/' . $old['name']);
        }
    }
}
