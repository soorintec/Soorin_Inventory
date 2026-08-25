<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * دسته کالا — سطح اول از ساختار سه‌سطحی (دسته ← کالا ← ورژن).
 * دسته می‌تواند زیردسته داشته باشد (مثال: ترک‌بال ← ترک‌بال کوچک؟ خیر —
 * زیردسته برای گروه‌بندی خودِ دسته‌هاست، نه برای کالا. کالا مستقیم زیر
 * یک دسته می‌آید).
 *
 * spec_template قالب مشخصات فنی این دسته را تعیین می‌کند:
 *   [{"key":"cpu","label":"پردازنده","type":"string"}, ...]
 * مقادیر واقعی روی ItemVersion::specs طبق همین قالب ذخیره می‌شود.
 */
class ItemCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['parent_id', 'name', 'code', 'spec_template'];

    protected function casts(): array
    {
        return ['spec_template' => 'array'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /** فهرست کلیدهای مشخصات فنی این دسته — برای ساخت فرم پویا. */
    public function specKeys(): array
    {
        return collect($this->spec_template ?? [])->pluck('label', 'key')->all();
    }
}
