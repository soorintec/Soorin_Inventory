<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\SystemModel;
use App\Services\SystemDuplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * کپیِ مدل سامانه و نسخه — با همهٔ نسخه‌ها و قطعات.
 */
class SystemDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private SystemModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $category = ItemCategory::create(['name' => 'قطعه']);
        $mon = Item::create(['item_category_id' => $category->id, 'code' => 'MON-1', 'name' => 'مانیتور']);
        $cbl = Item::create(['item_category_id' => $category->id, 'code' => 'CBL-1', 'name' => 'کابل']);

        $this->model = SystemModel::create(['code' => 'TITAN', 'name' => 'تایتان', 'description' => 'توضیح']);

        $v1 = $this->model->versions()->create(['version_code' => '1403', 'year' => 1403, 'notes' => 'یادداشت']);
        $v1->bomLines()->create(['item_id' => $mon->id, 'quantity' => 2, 'is_optional' => false]);
        $v1->bomLines()->create(['item_id' => $cbl->id, 'quantity' => 3, 'is_optional' => true]);

        $v2 = $this->model->versions()->create(['version_code' => '1404', 'year' => 1404]);
        $v2->bomLines()->create(['item_id' => $mon->id, 'quantity' => 1]);
    }

    public function test_duplicating_a_model_copies_all_versions_and_bom(): void
    {
        $copy = app(SystemDuplicationService::class)->duplicateModel($this->model, 'تایتان جدید');

        $this->assertNotSame($this->model->id, $copy->id);
        $this->assertSame('تایتان جدید', $copy->name);
        $this->assertNotSame($this->model->code, $copy->code);      // کد یکتا شد
        $this->assertStringContainsString('COPY', $copy->code);

        // دو نسخه با همان کدها کپی شده‌اند.
        $this->assertEqualsCanonicalizing(
            ['1403', '1404'],
            $copy->versions()->pluck('version_code')->all(),
        );

        // قطعاتِ هر نسخه هم کپی شده (۲ + ۱ = ۳ ردیف).
        $this->assertSame(3, $copy->bomLines()->count());

        // ردیف‌ها کپیِ تازه‌اند، نه اشاره به ردیف‌های اصلی. (bomLines یک
        // HasManyThrough است، پس ستونِ id باید صریح باشد تا مبهم نشود.)
        $originalBomIds = $this->model->bomLines()->pluck('system_bom_lines.id')->all();
        foreach ($copy->bomLines()->pluck('system_bom_lines.id')->all() as $id) {
            $this->assertNotContains($id, $originalBomIds);
        }
    }

    public function test_duplicate_model_code_stays_unique_on_repeat(): void
    {
        $a = app(SystemDuplicationService::class)->duplicateModel($this->model, 'کپی ۱');
        $b = app(SystemDuplicationService::class)->duplicateModel($this->model, 'کپی ۲');

        $this->assertNotSame($a->code, $b->code);
    }

    public function test_duplicating_a_version_copies_it_within_the_same_model(): void
    {
        $version = $this->model->versions()->where('version_code', '1403')->firstOrFail();

        $copy = app(SystemDuplicationService::class)->duplicateVersion($version, '1403-B');

        $this->assertSame($this->model->id, $copy->system_model_id);   // همان مدل
        $this->assertSame('1403-B', $copy->version_code);
        $this->assertSame(2, $copy->bomLines()->count());              // هر دو قطعه کپی شد
        $this->assertNotSame($version->id, $copy->id);

        // مدل حالا سه نسخه دارد.
        $this->assertSame(3, $this->model->versions()->count());
    }
}
