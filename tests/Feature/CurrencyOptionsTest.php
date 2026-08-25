<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * قیمت‌گذاری قطعه‌ها باید واحد پول را از جدول ارز بخواند، نه فهرست ثابت.
 * افزودن یک ارز تازه (مثلاً روبل) باید همه‌جا در دسترس شود.
 */
class CurrencyOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_rial_is_always_present_and_first(): void
    {
        // حتی با جدول خالی، ریال هست و اول است.
        $options = Currency::options();

        $this->assertSame('IRR', array_key_first($options));
        $this->assertSame('ریال', $options['IRR']);
    }

    public function test_added_currency_appears_in_options(): void
    {
        Currency::create(['code' => 'RUB', 'name' => 'روبل']);

        $options = Currency::options();

        $this->assertArrayHasKey('RUB', $options);
        $this->assertSame('روبل', $options['RUB']);
        // ریال همچنان اول می‌ماند
        $this->assertSame('IRR', array_key_first($options));
    }

    public function test_label_reads_from_the_currency_table(): void
    {
        Currency::create(['code' => 'RUB', 'name' => 'روبل']);

        $this->assertSame('روبل', Currency::label('RUB'));
        $this->assertSame('ریال', Currency::label('IRR'));
        $this->assertSame('ریال', Currency::label(null));
        // ارز ناشناخته → خودِ کد
        $this->assertSame('XYZ', Currency::label('XYZ'));
    }

    public function test_item_version_label_uses_the_added_currency(): void
    {
        Currency::create(['code' => 'RUB', 'name' => 'روبل']);

        $category = ItemCategory::create(['name' => 'دسته', 'code' => 'C1']);
        $item = Item::create(['item_category_id' => $category->id, 'code' => 'K1', 'name' => 'کالا', 'unit' => 'عدد']);
        $version = ItemVersion::create([
            'item_id' => $item->id, 'version_code' => 'V1',
            'fx_price' => 500, 'fx_currency' => 'RUB',
        ]);

        $this->assertSame('روبل', $version->currencyLabel());
        $this->assertStringContainsString('روبل', $version->fxPriceLabel());
        $this->assertTrue($version->isImported()); // غیرریالی = وارداتی
    }

    public function test_cache_refreshes_when_a_currency_is_added(): void
    {
        $this->assertArrayNotHasKey('RUB', Currency::options()); // کش اولیه

        Currency::create(['code' => 'RUB', 'name' => 'روبل']);

        // هوک saved باید کش را پاک کرده باشد
        $this->assertArrayHasKey('RUB', Currency::options());
    }
}
