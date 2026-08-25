<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\SystemModel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DatabaseBackupService;
use App\Services\StockMovementService;
use App\Services\StocktakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * سخت‌ترین آزمون پشتیبان: **کل دیتابیس پاک می‌شود** و از فایل برگردانده
 * می‌شود. اگر چیزی از قلم بیفتد، اینجا لو می‌رود.
 *
 * تراکنش RefreshDatabase اینجا کارساز نیست چون DROP TABLE به‌طور ضمنی
 * commit می‌کند؛ به همین دلیل هر تست خودش وضعیت را از نو می‌سازد.
 */
class BackupSurvivesWipeTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseBackupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DatabaseBackupService::class);

        foreach ($this->service->list() as $old) {
            $this->service->delete($old['name']);
        }
    }

    /**
     * ساخت داده‌ای که تمام گوشه‌های سامانه را لمس کند: کاربر و مجوز، کالا و
     * ورژن، لات FIFO، تراکنش، انبارگردانی، سیاهه تغییرات، و متن فارسی با
     * نقل‌قول و نقطه‌ویرگول.
     */
    private function buildRealisticData(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'مدیر انبار', 'email' => 'admin@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $admin->forceFill(['last_login_at' => now()])->save();
        $this->actingAs($admin);

        $category = ItemCategory::create(['name' => 'کابل و رابط', 'code' => 'CBL']);

        $item = Item::create([
            'item_category_id' => $category->id,
            'code'             => 'CBL-001',
            'name'             => 'کابل VGA',
            'unit'             => 'عدد',
            'description'      => "توضیح با ' نقل‌قول و ; نقطه‌ویرگول و \"دابل کوت\" و \\ بک‌اسلش",
        ]);

        $version = $item->versions()->create([
            'version_code' => 'کشوی راست میانی',
            'location'     => 'D3/#04',
            'notes'        => 'یک عدد معیوب',
            'fx_price'     => 12.50,
            'fx_currency'  => 'USD',
            'min_stock'    => 5,
        ]);

        $warehouse = Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);

        $stock = app(StockMovementService::class);
        $stock->recordIn($version, $warehouse, 21, 850_000, StockMovement::REASON_INITIAL);
        $stock->recordIn($version, $warehouse, 9, 920_000, StockMovement::REASON_PURCHASE);
        $stock->recordOut($version, $warehouse, 4, StockMovement::REASON_PROJECT);

        $model = SystemModel::create(['code' => 'TITAN', 'name' => 'کنسول ناوبری تایتان']);
        $systemVersion = $model->versions()->create(['version_code' => '1404', 'year' => 1404]);
        $systemVersion->bomLines()->create(['item_id' => $item->id, 'quantity' => 2]);

        app(StocktakeService::class)->start($warehouse, 'انبارگردانی آزمایشی');

        // کالای حذف‌شده هم باید برگردد — حذف نرم است، پس داده‌اش هنوز هست
        $doomed = Item::create([
            'item_category_id' => $category->id, 'code' => 'CBL-002', 'name' => 'کابل حذف‌شده',
        ]);
        $doomed->delete();
    }

    /** @return array<string, int|string|null> */
    private function snapshot(): array
    {
        return [
            'users'            => User::count(),
            'permissions'      => DB::table('model_has_permissions')->count(),
            'categories'       => ItemCategory::count(),
            'items'            => Item::count(),
            'items_trashed'    => Item::onlyTrashed()->count(),
            'versions'         => ItemVersion::count(),
            'warehouses'       => Warehouse::count(),
            'movements'        => StockMovement::count(),
            'lots'             => StockLot::count(),
            'balance_qty'      => (string) StockBalance::sum('quantity'),
            'lot_remaining'    => (string) StockLot::sum('quantity_remaining'),
            'activity'         => ActivityLog::count(),
            'stocktakes'       => Stocktake::count(),
            'stocktake_lines'  => DB::table('stocktake_lines')->count(),
            'bom_lines'        => DB::table('system_bom_lines')->count(),
            'item_description' => Item::where('code', 'CBL-001')->value('description'),
            'version_notes'    => ItemVersion::where('version_code', 'کشوی راست میانی')->value('notes'),
            'fx_price'         => (string) ItemVersion::where('version_code', 'کشوی راست میانی')->value('fx_price'),
            'admin_email'      => User::where('user_type', User::TYPE_ADMIN)->value('email'),
        ];
    }

    /** پاک کردن واقعی همه جدول‌ها — شبیه‌سازی «کل دیتابیس پاک شد». */
    private function wipeEverything(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (DB::select('SHOW TABLES') as $row) {
            $table = array_values((array) $row)[0];
            DB::statement("DROP TABLE IF EXISTS `{$table}`");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * آزمون اصلی: پشتیبان بگیر، کل دیتابیس را نابود کن، برگردان، و مطمئن شو
     * هیچ چیز از دست نرفته.
     */
    public function test_a_backup_restores_everything_after_the_database_is_wiped(): void
    {
        $this->buildRealisticData();

        $before = $this->snapshot();
        $this->assertGreaterThan(0, $before['movements'], 'داده آزمون باید واقعاً ساخته شده باشد');

        $backup = $this->service->create();
        $path = $this->service->absolutePath($backup);

        // کل دیتابیس نابود می‌شود
        $this->wipeEverything();
        $this->assertCount(0, DB::select('SHOW TABLES'), 'دیتابیس باید واقعاً خالی شده باشد');

        $this->service->restore($path);

        $after = $this->snapshot();

        /*
        | سیاهه تغییرات جدا بررسی می‌شود: خودِ بازیابی هم یک سطر ثبت می‌کند،
        | پس یکی بیشتر شدنش درست است — نه نشانه از دست رفتن داده.
        */
        $this->assertSame(
            $before['activity'] + 1,
            $after['activity'],
            'همه سطرهای سیاهه باید برگردند، به‌علاوه ثبت خودِ بازیابی',
        );
        $this->assertTrue(
            ActivityLog::where('action', 'backup_restored')->exists(),
            'خودِ بازیابی باید در سیاهه ثبت شده باشد',
        );

        unset($before['activity'], $after['activity']);

        foreach ($before as $key => $value) {
            $this->assertSame($value, $after[$key], "«{$key}» پس از بازیابی با قبل فرق دارد");
        }
    }

    /**
     * بازیابی روی دیتابیس کاملاً خالی باید کار کند.
     *
     * این دقیقاً همان لحظه‌ای است که پشتیبان به درد می‌خورد. پیش از اصلاح،
     * restore اول می‌خواست «پشتیبان ایمنی» بگیرد و آن هم می‌خواست در جدول
     * activity_logs بنویسد — جدولی که دیگر وجود نداشت — پس بازیابی درست در
     * بدترین لحظه شکست می‌خورد.
     */
    public function test_restoring_onto_a_completely_empty_database_works(): void
    {
        $this->buildRealisticData();

        $backup = $this->service->create();
        $path = $this->service->absolutePath($backup);

        $this->wipeEverything();

        $safety = $this->service->restore($path);

        $this->assertNull($safety, 'روی دیتابیس خالی، پشتیبان ایمنی لازم نیست');
        $this->assertSame(1, User::where('user_type', User::TYPE_ADMIN)->count());
        $this->assertSame(2, Item::withTrashed()->count());
    }

    /** ساختار جدول‌ها هم باید برگردد، نه فقط ردیف‌ها. */
    public function test_the_schema_comes_back_too(): void
    {
        $this->buildRealisticData();

        $tablesBefore = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->sort()->values()->all();

        $backup = $this->service->create();
        $this->wipeEverything();
        $this->service->restore($this->service->absolutePath($backup));

        $tablesAfter = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->sort()->values()->all();

        $this->assertSame($tablesBefore, $tablesAfter);
    }

    /** پس از بازیابی باید بشود دوباره کار کرد — نه فقط داده خواند. */
    public function test_the_system_still_works_after_a_restore(): void
    {
        $this->buildRealisticData();

        $backup = $this->service->create();
        $this->wipeEverything();
        $this->service->restore($this->service->absolutePath($backup));

        $admin = User::where('user_type', User::TYPE_ADMIN)->firstOrFail();
        $this->actingAs($admin);

        // مجوزها باید سالم برگشته باشند
        $this->assertTrue($admin->can(\App\Enums\Permission::ManageStock->value));

        // و ثبت تراکنش تازه باید کار کند (AUTO_INCREMENT سالم باشد)
        $version = ItemVersion::where('version_code', 'کشوی راست میانی')->firstOrFail();
        $warehouse = Warehouse::where('code', 'MAIN')->firstOrFail();

        $before = (float) StockBalance::where('item_version_id', $version->id)->sum('quantity');

        app(StockMovementService::class)->recordIn(
            $version, $warehouse, 3, 500_000, StockMovement::REASON_PURCHASE,
        );

        $this->assertEquals(
            $before + 3,
            (float) StockBalance::where('item_version_id', $version->id)->sum('quantity'),
        );
    }

    /** FIFO باید پس از بازیابی همان قیمت‌ها را بدهد. */
    public function test_fifo_costs_survive_the_restore(): void
    {
        $this->buildRealisticData();

        $lotsBefore = StockLot::orderBy('id')
            ->get(['unit_cost', 'quantity_in', 'quantity_remaining'])
            ->map(fn ($lot) => $lot->only(['unit_cost', 'quantity_in', 'quantity_remaining']))
            ->all();

        $backup = $this->service->create();
        $this->wipeEverything();
        $this->service->restore($this->service->absolutePath($backup));

        $lotsAfter = StockLot::orderBy('id')
            ->get(['unit_cost', 'quantity_in', 'quantity_remaining'])
            ->map(fn ($lot) => $lot->only(['unit_cost', 'quantity_in', 'quantity_remaining']))
            ->all();

        $this->assertSame($lotsBefore, $lotsAfter);
    }

    protected function tearDown(): void
    {
        // اگر تستی وسط کار جدول‌ها را نابود کرده باشد، RefreshDatabase در
        // تست بعدی از نو می‌سازد؛ فقط فایل‌های پشتیبان را پاک می‌کنیم.
        try {
            foreach ($this->service->list() as $file) {
                Storage::disk('local')->delete('backups/' . $file['name']);
            }
        } catch (\Throwable) {
            // پاک‌سازی فایل نباید خطای تست بسازد
        }

        parent::tearDown();
    }
}
