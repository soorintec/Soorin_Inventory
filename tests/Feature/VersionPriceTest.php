<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * قیمت هر ورژن با ارز خودش (ریال/دلار/یوان) و ارزش موجودی به تفکیک ارز —
 * بدون هیچ تبدیل بین ارزها.
 */
class VersionPriceTest extends TestCase
{
    use RefreshDatabase;

    private function version(float $price, string $currency): ItemVersion
    {
        $category = ItemCategory::firstOrCreate(['name' => 'کابل و رابط']);
        $item = Item::create([
            'item_category_id' => $category->id,
            'code'             => 'C-' . uniqid(),
            'name'             => 'کالای آزمایشی',
        ]);

        return $item->versions()->create([
            'version_code' => 'اصلی', 'fx_price' => $price, 'fx_currency' => $currency,
        ]);
    }

    public function test_rial_price_is_labelled_in_rial(): void
    {
        $version = $this->version(850_000, 'IRR');

        $this->assertSame('۸۵۰٬۰۰۰ ریال', $version->fxPriceLabel());
        $this->assertFalse($version->isImported());   // ریالی وارداتی نیست
        $this->assertTrue($version->hasPrice());
    }

    public function test_foreign_price_keeps_decimals_and_counts_as_imported(): void
    {
        $version = $this->version(12.5, 'USD');

        $this->assertSame('۱۲٫۵ دلار', $version->fxPriceLabel());
        $this->assertTrue($version->isImported());
    }

    public function test_a_version_without_a_price_has_no_label(): void
    {
        $version = $this->version(0, 'IRR');
        $version->update(['fx_price' => null]);

        $this->assertNull($version->fresh()->fxPriceLabel());
        $this->assertFalse($version->fresh()->hasPrice());
    }

    public function test_stock_value_is_summed_separately_per_currency(): void
    {
        $warehouse = Warehouse::create(['name' => 'مرکزی', 'code' => 'MAIN']);

        // ۱۰ عدد ریالی ۵۰۰٬۰۰۰ و ۴ عدد دلاری ۱۲٫۵ — نباید با هم جمع شوند
        foreach ([[500_000, 'IRR', 10], [12.5, 'USD', 4], [3, 'CNY', 2]] as [$price, $cur, $qty]) {
            $version = $this->version($price, $cur);
            StockBalance::create([
                'item_version_id' => $version->id,
                'warehouse_id'    => $warehouse->id,
                'quantity'        => $qty,
            ]);
        }

        $totals = app(InventoryReportService::class)->stockValueByCurrency();

        $this->assertSame(5_000_000.0, $totals['IRR']['value']);   // ۵۰۰٬۰۰۰ × ۱۰
        $this->assertSame(50.0, $totals['USD']['value']);          // ۱۲٫۵ × ۴
        $this->assertSame(6.0, $totals['CNY']['value']);           // ۳ × ۲

        // ترتیب خروجی همیشه ریال، دلار، یوان است
        $this->assertSame(['IRR', 'USD', 'CNY'], array_keys($totals));
    }

    public function test_new_versions_default_to_rial(): void
    {
        $category = ItemCategory::firstOrCreate(['name' => 'کابل و رابط']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'X-1', 'name' => 'کالا']);
        $version = $item->versions()->create(['version_code' => 'اصلی']);

        $this->assertSame('IRR', $version->fresh()->fx_currency);
    }
}
