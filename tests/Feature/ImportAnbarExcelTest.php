<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * واردات فایل اکسل انبار. فایل آزمایشی همان ساختار فایل واقعی شرکت را دارد:
 * سه سطر عنوان بالا، و ستون‌های ردیف | نام کالا | مشخصات | موجودی | کاربرد |
 * توضیح | محل استقرار.
 */
class ImportAnbarExcelTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create(['name' => 'انباردار', 'email' => 'w@dpst.ir', 'password' => 'secret123']);
        $this->actingAs($user);

        Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);

        $this->path = $this->makeWorkbook([
            ['1', 'کابل VGA',      '',        '16',   'کنسول',  'یک عدد معیوب',  'کمد انبار'],
            ['2', 'کابل VGA',      '',        '5',    '',       'در اتاق کار',   'کشوی راست میانی'],
            ['3', 'RAM DDR3 2GB',  'PC',      '12',   '',       '',              'D3/#04'],
            ['4', 'RAM DDR3 2GB',  'Laptop',  '2',    '',       '',              'D3/#04'],
            ['5', 'اسپیکر TSCO',   '',        '3+2',  '',       '',              'C2'],
            ['6', 'کابل شبکه خام', '',        '63 m', '',       '',              'A1'],
            ['7', 'اسپیکر سورین',  '',        '0',    '',       'موجودی نداریم', 'B3'],
            ['8', 'مانیتور ۲۲ اینچ', '',      '۸۷',   '',       '',              'کارتون'],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    /** @param array<int, array<int, string>> $rows */
    private function makeWorkbook(array $rows, string $sheetName = 'انبار'): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($sheetName);

        // سه سطر عنوان که دستور باید نادیده بگیرد
        $sheet->fromArray([['لیست انبار شرکت'], [''], ['ردیف', 'نام کالا', 'مشخصات', 'موجودی', 'کاربرد', 'توضیح', 'محل استقرار']], null, 'A1');
        $sheet->fromArray($rows, null, 'A4');

        // شیتی که نباید خوانده شود
        $book->createSheet()->setTitle('معیوب')->fromArray([['ردیف', 'نام کالا'], ['1', 'کالای معیوب']], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'anbar') . '.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    private function import(array $options = []): void
    {
        $this->artisan('inventory:import-anbar', ['file' => $this->path] + $options)->assertSuccessful();
    }

    public function test_it_creates_items_versions_and_stock(): void
    {
        $this->import();

        $this->assertSame(6, Item::count());        // ۸ سطر، ولی «کابل VGA» و «RAM» هرکدام دو سطر دارند
        $this->assertSame(8, ItemVersion::count());
        $this->assertEquals(190, StockBalance::sum('quantity'));   // ۱۶+۵+۱۲+۲+۵+۶۳+۰+۸۷
    }

    public function test_it_ignores_other_sheets(): void
    {
        $this->import();

        $this->assertSame(0, Item::where('name', 'کالای معیوب')->count());
    }

    public function test_it_splits_versions_by_spec_then_by_location(): void
    {
        $this->import();

        $ram = Item::where('name', 'RAM DDR3 2GB')->firstOrFail();
        $this->assertEqualsCanonicalizing(['PC', 'Laptop'], $ram->versions->pluck('version_code')->all());

        // «کابل VGA» مشخصات ندارد، پس ورژن دوم با آدرس قفسه از اولی جدا می‌شود
        $vga = Item::where('name', 'کابل VGA')->firstOrFail();
        $this->assertEqualsCanonicalizing(['اصلی', 'کشوی راست میانی'], $vga->versions->pluck('version_code')->all());
    }

    public function test_it_stores_the_shelf_address_on_the_version(): void
    {
        $this->import();

        $this->assertSame('D3/#04', ItemVersion::where('version_code', 'PC')->value('location'));
    }

    /**
     * ستون «توضیحات» اکسل هم روی ورژن می‌نشیند (تا انباردار ببیندش) و هم روی
     * سند ورود (تا سابقه لحظه شمارش بماند).
     */
    public function test_it_stores_the_warehouse_note_in_both_places(): void
    {
        $this->import();

        $version = ItemVersion::where('version_code', 'اصلی')
            ->whereHas('item', fn ($q) => $q->where('name', 'کابل VGA'))
            ->firstOrFail();

        $this->assertSame('یک عدد معیوب', $version->notes);
        $this->assertStringContainsString(
            'یک عدد معیوب',
            StockMovement::where('item_version_id', $version->id)->value('notes'),
        );
    }

    /** ستون «کاربری» توصیف خود کالاست، پس روی کالا می‌نشیند نه روی ورژن. */
    public function test_it_stores_the_usage_column_on_the_item(): void
    {
        $this->import();

        $this->assertSame('کنسول', Item::where('name', 'کابل VGA')->value('description'));
    }

    /** ورژن با موجودی صفر سند ورود ندارد، ولی یادداشتش نباید گم شود. */
    public function test_a_zero_stock_version_still_keeps_its_note(): void
    {
        $this->import();

        $version = Item::where('name', 'اسپیکر سورین')->firstOrFail()->versions->firstOrFail();

        $this->assertSame('موجودی نداریم', $version->notes);
    }

    /**
     * اجرای دوباره روی داده‌ای که این ستون‌ها را ندارد باید خانه خالی را پر کند،
     * ولی چیزی را که کاربر خودش نوشته بازنویسی نکند.
     */
    public function test_re_running_backfills_empty_fields_without_overwriting_edits(): void
    {
        $this->import();

        $version = ItemVersion::where('version_code', 'PC')->firstOrFail();
        $version->update(['location' => null, 'notes' => 'یادداشت دستی کاربر']);

        $this->import();
        $version->refresh();

        $this->assertSame('D3/#04', $version->location);            // خانه خالی پر شد
        $this->assertSame('یادداشت دستی کاربر', $version->notes);   // نوشته کاربر دست‌نخورده
    }

    public function test_it_parses_awkward_quantities(): void
    {
        $this->import();

        // «3+2» یعنی دو محل نگهداری از یک کالا
        $speaker = Item::where('name', 'اسپیکر TSCO')->firstOrFail();
        $this->assertEquals(5, $speaker->totalStock());

        // «63 m» یعنی واحد این کالا متر است، نه عدد
        $cable = Item::where('name', 'کابل شبکه خام')->firstOrFail();
        $this->assertSame('متر', $cable->unit);
        $this->assertEquals(63, $cable->totalStock());

        // اعداد فارسی هم باید خوانده شوند
        $this->assertEquals(87, Item::where('name', 'مانیتور ۲۲ اینچ')->firstOrFail()->totalStock());
    }

    public function test_it_does_not_create_a_movement_for_zero_stock(): void
    {
        $this->import();

        $version = Item::where('name', 'اسپیکر سورین')->firstOrFail()->versions->first();

        $this->assertNotNull($version);      // ورژن ساخته می‌شود تا کالا در فهرست بماند
        $this->assertSame(0, StockMovement::where('item_version_id', $version->id)->count());
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->import();
        $this->import();

        $this->assertSame(6, Item::count());
        $this->assertSame(8, ItemVersion::count());
        $this->assertEquals(190, StockBalance::sum('quantity'));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->import(['--dry-run' => true]);

        $this->assertSame(0, Item::count());
        $this->assertSame(0, ItemCategory::count());
    }

    public function test_it_guesses_the_category_from_the_name(): void
    {
        $this->import();

        $this->assertSame('کابل و رابط', Item::where('name', 'کابل VGA')->firstOrFail()->category->name);
        $this->assertSame('قطعات کامپیوتر', Item::where('name', 'RAM DDR3 2GB')->firstOrFail()->category->name);
        $this->assertSame('صوتی', Item::where('name', 'اسپیکر TSCO')->firstOrFail()->category->name);
        $this->assertSame('نمایشگر', Item::where('name', 'مانیتور ۲۲ اینچ')->firstOrFail()->category->name);
    }

    public function test_it_fails_loudly_on_a_missing_sheet(): void
    {
        $this->artisan('inventory:import-anbar', ['file' => $this->path, '--sheet' => 'ندارد'])->assertFailed();
    }
}
