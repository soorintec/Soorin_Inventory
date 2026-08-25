<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemVersion;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

/**
 * ثبت تغییرات کالا، ورژن، دسته و انبار در سیاهه تغییرات.
 *
 * پیش از این فقط ورود و خروج انبار ثبت می‌شد، پس سؤال «چه کسی نام این کالا را
 * عوض کرد؟» جوابی نداشت.
 *
 * فقط ستون‌های تغییرکرده ذخیره می‌شوند، نه کل رکورد — سیاهه باید خوانا بماند.
 */
class CatalogueObserver
{
    /** نگاشت مدل به پیشوند نوع تغییر. */
    private const PREFIX = [
        Item::class         => 'item',
        ItemVersion::class  => 'version',
        ItemCategory::class => 'category',
        Warehouse::class    => 'warehouse',
    ];

    public function created(Model $model): void
    {
        $this->log($model, 'created');
    }

    public function updated(Model $model): void
    {
        $changed = $this->changedColumns($model);

        // ذخیره‌ای که چیزی را عوض نکرده، ارزش ثبت ندارد
        if ($changed === []) {
            return;
        }

        $this->log($model, 'updated', ['fields' => $changed]);
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'deleted');
    }

    private function log(Model $model, string $verb, array $changes = []): void
    {
        $prefix = self::PREFIX[$model::class] ?? null;

        if ($prefix === null) {
            return;
        }

        ActivityLog::record("{$prefix}_{$verb}", $model, $changes);
    }

    /**
     * نام ستون‌هایی که واقعاً عوض شده‌اند.
     *
     * @return array<int, string>
     */
    private function changedColumns(Model $model): array
    {
        return collect($model->getChanges())
            ->keys()
            ->reject(fn (string $column) => in_array($column, ['updated_at', 'created_at'], true))
            ->values()
            ->all();
    }
}
