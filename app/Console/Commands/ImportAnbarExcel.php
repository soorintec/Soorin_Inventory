<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockMovementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * وارد کردن فایل اکسل انبار موجود شرکت به سامانه.
 *
 * فقط شیت «انبار» خوانده می‌شود — شیت‌های «معیوب» و «China» طبق تصمیم صریح
 * مالک پروژه وارد نمی‌شوند.
 *
 * دستور idempotent است: اجرای دوباره کالای تکراری نمی‌سازد و موجودی را دوباره
 * وارد نمی‌کند. تشخیص «قبلاً وارد شده» از روی سند حرکت انبار با یادداشت
 * IMPORT_TAG انجام می‌شود، نه از روی موجودی — چون ممکن است بعد از واردات
 * موجودی با مصرف عادی تغییر کرده باشد.
 */
class ImportAnbarExcel extends Command
{
    protected $signature = 'inventory:import-anbar
                            {file : مسیر فایل اکسل}
                            {--sheet=انبار : نام شیتی که خوانده می‌شود}
                            {--warehouse=MAIN : کد انباری که موجودی در آن ثبت می‌شود}
                            {--dry-run : فقط گزارش بده، چیزی در دیتابیس ننویس}';

    protected $description = 'وارد کردن شیت «انبار» از فایل اکسل موجودی شرکت';

    /** نشانه‌ای که روی حرکت‌های انبارِ ساخته‌شده توسط این دستور می‌نشیند. */
    private const IMPORT_TAG = 'واردات اولیه از فایل اکسل انبار';

    /**
     * قواعد حدس دسته‌بندی از روی نام کالا. ترتیب مهم است — اولین تطابق برنده است.
     *
     * «برق و تغذیه» و «کابل و رابط» عمداً پیش از «برد الکترونیکی» می‌آیند:
     * «آداپتور برد Canbass» آداپتور است نه برد، و «فلت مادربرد Asus» کابل فلت
     * است نه برد. مقایسه بدون حساسیت به بزرگی/کوچکی حروف انجام می‌شود چون در
     * فایل هم «Display Cable» آمده و هم «Display cable».
     *
     * @var array<int, array{0: string, 1: array<int, string>}>
     */
    private const CATEGORY_RULES = [
        ['پلکسی و قطعات ساخت', ['پلکسی', 'پلی کربنات', 'درب ذوزنقه', 'درپوش', 'سرولوم', 'شفت سکان', 'زانویی']],
        ['برق و تغذیه', ['آداپتور', 'شارژر', 'باتری', 'پاور', 'محافظ برق', 'سه راهی برق', 'چهار راهی برق', 'پریز', 'سیم سیار', 'power cable']],
        ['کابل و رابط', ['کابل', 'cable', 'caable', 'hdmi', 'سیم ', 'فلت', 'جک ', 'تبدیل', 'مبدل', 'اسپلیتر', 'رابط شبکه', 'اکستندر']],
        ['برد الکترونیکی', ['برد ', 'چیپ وایرلس', 'tpm', 'کیت و خازن']],
        ['نمایشگر', ['مانیتور', 'تلویزیون', 'تاچ ال سی دی', 'پنل']],
        ['تین‌کلاینت و کیس', ['تین کلاینت']],
        ['قطعات کامپیوتر', ['ram', 'هارد', 'رم ریدر', 'کارت شبکه', 'کارت صدا', 'فریم هارد', 'dvd', 'دیسک های driver', 'هاب', 'hub', 'دانگل']],
        ['ورودی و کنترل', ['ترکبال', 'موس', 'کیبورد', 'جوی استیک', 'کلید', 'دسته', 'سکان', 'چرخ کنسول']],
        ['صوتی', ['اسپیکر', 'هندست', 'اینترکام', 'میکروفون', 'بوغ', 'بوق', 'صدای']],
        ['شبکه', ['سوییچ', 'kvm', 'شبکه']],
        ['ابزار و مصرفی', ['اسپری', 'چسب', 'فوم', 'بتونه', 'رنگ', 'سرنگ', 'هوویه', 'اهم متر', 'گیره', 'جعبه ابزار', 'پرگار', 'بلوور', 'کلروفروم', 'کلروفرم', 'برچسب', 'جعبه سیم', 'پایه']],
    ];

    private const FALLBACK_CATEGORY = 'متفرقه';

    /** پیشوند کد کالا برای هر دسته — کد نهایی مثل «CBL-007». */
    private const CATEGORY_CODES = [
        'پلکسی و قطعات ساخت' => 'PLX',
        'برق و تغذیه'        => 'PWR',
        'کابل و رابط'        => 'CBL',
        'برد الکترونیکی'     => 'PCB',
        'نمایشگر'            => 'MON',
        'تین‌کلاینت و کیس'   => 'TCL',
        'قطعات کامپیوتر'     => 'CMP',
        'ورودی و کنترل'      => 'INP',
        'صوتی'               => 'AUD',
        'شبکه'               => 'NET',
        'ابزار و مصرفی'      => 'TOL',
        'متفرقه'             => 'MSC',
    ];

    /**
     * قالب مشخصات فنی چند دسته‌ای که واقعاً مشخصه تکرارشونده دارند. بقیه
     * دسته‌ها قالب نمی‌گیرند تا فرم ورژن بی‌دلیل شلوغ نشود؛ هر وقت لازم شد
     * از خود پنل اضافه می‌شود.
     *
     * @var array<string, array<int, array{key: string, label: string}>>
     */
    private const SPEC_TEMPLATES = [
        'نمایشگر' => [
            ['key' => 'inch', 'label' => 'اینچ'],
            ['key' => 'resolution', 'label' => 'رزولوشن'],
            ['key' => 'touch', 'label' => 'لمسی'],
        ],
        'تین‌کلاینت و کیس' => [
            ['key' => 'cpu', 'label' => 'پردازنده'],
            ['key' => 'ram', 'label' => 'رم'],
            ['key' => 'storage', 'label' => 'حافظه'],
        ],
        'کابل و رابط' => [
            ['key' => 'length', 'label' => 'طول'],
            ['key' => 'connector', 'label' => 'نوع کانکتور'],
        ],
        'برق و تغذیه' => [
            ['key' => 'voltage', 'label' => 'ولتاژ'],
            ['key' => 'current', 'label' => 'جریان'],
        ],
    ];

    public function handle(StockMovementService $stock): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("فایل پیدا نشد: {$path}");

            return self::FAILURE;
        }

        $warehouse = Warehouse::where('code', $this->option('warehouse'))->first();

        if (! $warehouse) {
            $this->error("انبار با کد «{$this->option('warehouse')}» وجود ندارد.");

            return self::FAILURE;
        }

        $rows = $this->readRows($path, $this->option('sheet'));

        if ($rows === null) {
            return self::FAILURE;
        }

        $this->info('ردیف خوانده‌شده از شیت «' . $this->option('sheet') . "»: " . count($rows));

        $plan = $this->buildPlan($rows);

        foreach ($plan['warnings'] as $warning) {
            $this->warn('  ⚠ ' . $warning);
        }

        if ($this->option('dry-run')) {
            $this->reportPlan($plan);
            $this->comment('حالت آزمایشی — چیزی در دیتابیس نوشته نشد.');

            return self::SUCCESS;
        }

        $result = DB::transaction(fn () => $this->write($plan, $warehouse, $stock));

        $this->newLine();
        $this->info('دسته ساخته‌شده: ' . $result['categories']);
        $this->info('کالای ساخته‌شده: ' . $result['items'] . ' (تکراری و رد شده: ' . $result['items_skipped'] . ')');
        $this->info('ورژن ساخته‌شده: ' . $result['versions'] . ' (تکراری و رد شده: ' . $result['versions_skipped']
            . '، خانه خالی پر شد: ' . $result['versions_backfilled'] . ')');
        $this->info('سند ورود موجودی: ' . $result['movements'] . ' — مجموع ' . $result['quantity'] . ' واحد');

        return self::SUCCESS;
    }

    /**
     * خواندن ردیف‌های خام شیت. سه سطر اول فایل سرصفحه و عنوان است؛
     * سطرهایی که ستون نام خالی دارند یا خودشان سرصفحه تکراری‌اند رد می‌شوند.
     *
     * @return array<int, array{excel_row: int, name: string, spec: string, qty_raw: string, usage: string, notes: string, address: string}>|null
     */
    private function readRows(string $path, string $sheetName): ?array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        // وجود شیت پیش از load بررسی می‌شود؛ setLoadSheetsOnly با نام اشتباه
        // کتاب کاری بدون هیچ شیت می‌سازد و phpspreadsheet خطای گنگ می‌دهد.
        $available = $reader->listWorksheetNames($path);

        if (! in_array($sheetName, $available, true)) {
            $this->error("شیت «{$sheetName}» در این فایل وجود ندارد. شیت‌های موجود: " . implode('، ', $available));

            return null;
        }

        $reader->setLoadSheetsOnly([$sheetName]);
        $sheet = $reader->load($path)->getSheetByName($sheetName);

        $rows = [];

        foreach ($sheet->toArray(null, true, false, false) as $index => $raw) {
            if ($index < 3) {
                continue;   // سه سطر عنوان بالای فایل
            }

            $cells = array_map(
                fn ($cell) => trim((string) ($cell ?? '')),
                array_pad(array_slice($raw, 0, 7), 7, ''),
            );

            if ($cells[1] === '' || $cells[1] === 'نام کالا') {
                continue;
            }

            $rows[] = [
                'excel_row' => $index + 1,
                'name'      => $cells[1],
                'spec'      => $cells[2],
                'qty_raw'   => $cells[3],
                'usage'     => $cells[4],
                'notes'     => $cells[5],
                'address'   => $cells[6],
            ];
        }

        return $rows;
    }

    /**
     * تبدیل ردیف‌های خام به ساختار «کالا ← ورژن». هر نام یکتا یک کالا می‌شود و
     * هر ردیف یک ورژن از آن کالا.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{items: array<string, array<string, mixed>>, warnings: array<int, string>}
     */
    private function buildPlan(array $rows): array
    {
        $items = [];
        $warnings = [];

        foreach ($rows as $row) {
            [$quantity, $unit, $warning] = $this->parseQuantity($row['qty_raw']);

            if ($warning) {
                $warnings[] = "سطر {$row['excel_row']} | {$row['name']} | {$warning}";
            }

            $name = $row['name'];

            $items[$name] ??= [
                'category'    => $this->categorize($name),
                'unit'        => 'عدد',
                'description' => '',
                'versions'    => [],
            ];

            if ($unit !== 'عدد') {
                $items[$name]['unit'] = $unit;
            }

            // ستون «کاربرد» توصیف خود کالاست، پس روی کالا می‌نشیند نه روی ورژن.
            if ($row['usage'] !== '' && ($items[$name]['description'] ?? '') === '') {
                $items[$name]['description'] = $row['usage'];
            }

            $items[$name]['versions'][] = [
                'code'      => $this->versionCode($row, $items[$name]['versions']),
                'quantity'  => $quantity,
                'location'  => $row['address'],
                'notes'     => $row['notes'],
                'excel_row' => $row['excel_row'],
            ];
        }

        return ['items' => $items, 'warnings' => $warnings];
    }

    /**
     * کد ورژن از ستون مشخصات می‌آید. اگر خالی یا تکراری بود، آدرس قفسه به‌جای
     * آن می‌نشیند (در فایل، دو ورژن هم‌نامِ یک کالا همیشه در دو قفسه جدا بودند)
     * و در نهایت شماره‌گذاری از تصادف جلوگیری می‌کند.
     *
     * @param  array<int, array<string, mixed>>  $existing
     */
    private function versionCode(array $row, array $existing): string
    {
        $taken = array_column($existing, 'code');
        $code = $row['spec'] !== '' ? $row['spec'] : 'اصلی';

        if (in_array($code, $taken, true) && $row['address'] !== '') {
            $code = $row['address'];
        }

        $base = mb_substr($code, 0, 36);
        $suffix = 2;

        while (in_array($code, $taken, true)) {
            $code = $base . '-' . $suffix++;
        }

        return mb_substr($code, 0, 40);
    }

    /**
     * ستون موجودی در فایل همیشه عدد خالص نیست: «3+2» یعنی دو محل نگهداری و
     * «63 m» یعنی واحدْ متر است نه عدد.
     *
     * @return array{0: float, 1: string, 2: ?string}
     */
    private function parseQuantity(string $raw): array
    {
        $raw = trim($this->toEnglishDigits($raw));

        if (preg_match('/^\d+(\.\d+)?$/', $raw)) {
            return [(float) $raw, 'عدد', null];
        }

        if (preg_match('/^(\d+)\s*\+\s*(\d+)$/', $raw, $m)) {
            return [(float) $m[1] + (float) $m[2], 'عدد', "موجودی «{$raw}» جمع شد."];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*m$/i', $raw, $m)) {
            return [(float) $m[1], 'متر', 'واحد این کالا «متر» در نظر گرفته شد.'];
        }

        return [0.0, 'عدد', "موجودی «{$raw}» قابل تفسیر نبود — صفر ثبت شد."];
    }

    private function categorize(string $name): string
    {
        $needle = mb_strtolower($name);

        foreach (self::CATEGORY_RULES as [$category, $keywords]) {
            foreach ($keywords as $keyword) {
                if (str_contains($needle, $keyword)) {
                    return $category;
                }
            }
        }

        return self::FALLBACK_CATEGORY;
    }

    private function toEnglishDigits(string $value): string
    {
        return str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value,
        );
    }

    /**
     * نوشتن نگاشت در دیتابیس. قیمت تمام‌شده صفر ثبت می‌شود چون فایل اکسل قیمت
     * ندارد؛ خریدهای بعدی لات خودشان را با قیمت واقعی می‌سازند و FIFO از همان
     * لحظه درست کار می‌کند.
     *
     * @param  array{items: array<string, array<string, mixed>>, warnings: array<int, string>}  $plan
     * @return array<string, int|float>
     */
    private function write(array $plan, Warehouse $warehouse, StockMovementService $stock): array
    {
        $result = [
            'categories' => 0, 'items' => 0, 'items_skipped' => 0,
            'versions' => 0, 'versions_skipped' => 0, 'versions_backfilled' => 0,
            'movements' => 0, 'quantity' => 0.0,
        ];

        $categories = [];

        foreach ($plan['items'] as $name => $data) {
            $categoryName = $data['category'];

            if (! isset($categories[$categoryName])) {
                $category = ItemCategory::firstOrCreate(
                    ['name' => $categoryName],
                    [
                        'code'          => self::CATEGORY_CODES[$categoryName] ?? null,
                        'spec_template' => self::SPEC_TEMPLATES[$categoryName] ?? null,
                    ],
                );

                $result['categories'] += $category->wasRecentlyCreated ? 1 : 0;
                $categories[$categoryName] = $category;
            }

            $category = $categories[$categoryName];

            $item = Item::firstOrNew(['name' => $name]);

            if (! $item->exists) {
                $item->item_category_id = $category->id;
                $item->code = $this->nextItemCode($category);
                $item->unit = $data['unit'];
                $item->description = $data['description'] ?: null;
                $item->save();
                $result['items']++;
            } else {
                $result['items_skipped']++;
            }

            foreach ($data['versions'] as $versionData) {
                $version = ItemVersion::firstOrNew([
                    'item_id'      => $item->id,
                    'version_code' => $versionData['code'],
                ]);

                if ($version->exists) {
                    $result['versions_skipped']++;

                    // ورژن موجود بازنویسی نمی‌شود، ولی خانه خالی پر می‌شود تا
                    // اجرای دوباره روی داده قدیمی، اطلاعات جاافتاده را برگرداند.
                    $backfilled = false;

                    foreach (['location' => $versionData['location'], 'notes' => $versionData['notes']] as $field => $value) {
                        if ($value !== '' && blank($version->{$field})) {
                            $version->{$field} = $value;
                            $backfilled = true;
                        }
                    }

                    if ($backfilled) {
                        $version->save();
                        $result['versions_backfilled']++;
                    }
                } else {
                    $version->location = $versionData['location'] ?: null;
                    $version->notes = $versionData['notes'] ?: null;
                    $version->save();
                    $result['versions']++;
                }

                if ($versionData['quantity'] <= 0 || $this->alreadyImported($version, $warehouse)) {
                    continue;
                }

                // توضیح اکسل هم روی ورژن می‌نشیند (بالا) و هم روی سند ورود:
                // روی ورژن تا انباردار ببیندش، روی سند تا سابقه لحظه شمارش بماند.
                $notes = self::IMPORT_TAG . ' — سطر ' . $versionData['excel_row'];

                if ($versionData['notes'] !== '') {
                    $notes .= ' — ' . $versionData['notes'];
                }

                $stock->recordIn(
                    itemVersion: $version,
                    warehouse: $warehouse,
                    quantity: $versionData['quantity'],
                    unitCost: 0,
                    reason: StockMovement::REASON_INITIAL,
                    notes: $notes,
                );

                $result['movements']++;
                $result['quantity'] += $versionData['quantity'];
            }
        }

        return $result;
    }

    /** آیا موجودی این ورژن قبلاً با همین دستور وارد شده؟ */
    private function alreadyImported(ItemVersion $version, Warehouse $warehouse): bool
    {
        return StockMovement::where('item_version_id', $version->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('notes', 'like', self::IMPORT_TAG . '%')
            ->exists();
    }

    /** کد بعدی در دنباله همان دسته — «CBL-001»، «CBL-002». */
    private function nextItemCode(ItemCategory $category): string
    {
        $prefix = $category->code ?: 'ITM';

        $last = Item::where('code', 'like', $prefix . '-%')
            ->orderByDesc('code')
            ->value('code');

        $number = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;

        return sprintf('%s-%03d', $prefix, $number);
    }

    /** @param array{items: array<string, array<string, mixed>>, warnings: array<int, string>} $plan */
    private function reportPlan(array $plan): void
    {
        $counts = [];
        $quantity = 0.0;
        $versions = 0;

        foreach ($plan['items'] as $data) {
            $counts[$data['category']] = ($counts[$data['category']] ?? 0) + 1;
            $versions += count($data['versions']);
            $quantity += array_sum(array_column($data['versions'], 'quantity'));
        }

        arsort($counts);

        $this->newLine();
        $this->table(
            ['دسته', 'تعداد کالا'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($counts), $counts),
        );

        $this->info('کالای یکتا: ' . count($plan['items']) . ' | ورژن: ' . $versions . ' | مجموع موجودی: ' . $quantity);
    }
}
