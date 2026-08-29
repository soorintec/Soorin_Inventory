<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * کالا — سطح دوم (مثال: «ترک‌بال کوچک»). موجودی و قیمت اینجا ثبت نمی‌شود؛
 * هر دو روی ItemVersion هستند. این قاعده هرگز نباید شکسته شود.
 */
class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_category_id', 'code', 'name', 'brand', 'unit',
        'track_serial', 'is_active', 'description',
    ];

    protected $attributes = [
        'unit'         => 'عدد',
        'track_serial' => false,
        'is_active'    => true,
    ];

    protected function casts(): array
    {
        return [
            'track_serial' => 'boolean',
            'is_active'    => 'boolean',
        ];
    }

    /**
     * حذف کالا، ورژن‌هایش را هم حذف نرم می‌کند.
     *
     * کلید خارجی دیتابیس cascadeOnDelete است، ولی حذف نرم اصلاً DELETE
     * اجرا نمی‌کند، پس آبشار پایگاه‌داده راه نمی‌افتد و ورژن‌ها آواره
     * می‌ماندند — یعنی کالا ناپدید ولی ورژن‌هایش هنوز در فهرست‌ها.
     */
    protected static function booted(): void
    {
        static::deleting(function (Item $item) {
            if ($item->isForceDeleting()) {
                return;
            }

            $item->versions()->each(fn (ItemVersion $version) => $version->delete());
        });

        static::restoring(function (Item $item) {
            $item->versions()->onlyTrashed()->each(fn (ItemVersion $version) => $version->restore());
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ItemVersion::class);
    }

    /**
     * سریال‌های همه ورژن‌های این کالا.
     *
     * سریال روی ورژن ثبت می‌شود (چون موجودی هم آنجاست)، ولی کاربر انبار
     * سریال را در سطح کالا می‌بیند و می‌جوید — «این دستگاه با سریال فلان
     * کدام کالاست»، نه «کدام ورژن».
     */
    public function serials(): HasManyThrough
    {
        return $this->hasManyThrough(ItemSerial::class, ItemVersion::class);
    }

    /**
     * موجودی همه ورژن‌های این کالا در همه انبارها.
     *
     * وجودش برای این است که بشود با withSum در یک کوئری جمع موجودی را
     * گرفت و روی همان مرتب‌سازی کرد؛ totalStock() برای هر سطر یک کوئری
     * جدا می‌زند و در فهرست ۲۰۰تایی کمرشکن است.
     */
    public function balances(): HasManyThrough
    {
        return $this->hasManyThrough(StockBalance::class, ItemVersion::class);
    }

    /**
     * همهٔ حرکت‌های انبارِ این کالا (ورود/خروجِ همهٔ ورژن‌هایش) — منبعِ «کاردکس».
     * صریح تعریف شده تا حتی ورژن‌های حذف‌نرم‌شده هم در تاریخچه بمانند (کوئری در
     * صفحهٔ کاردکس withTrashed می‌زند؛ این رابطه برای مصرف‌های عادی است).
     */
    public function movements(): HasManyThrough
    {
        return $this->hasManyThrough(StockMovement::class, ItemVersion::class, 'item_id', 'item_version_id', 'id', 'id');
    }

    public const STATUS_OUT = 'out';    // موجودی صفر
    public const STATUS_LOW = 'low';    // روی حد هشدار یا کمتر
    public const STATUS_OK  = 'ok';     // بالاتر از حد هشدار

    /** موجودی کل کالا = جمع موجودی همه ورژن‌هایش در همه انبارها. */
    public function totalStock(): float
    {
        return (float) StockBalance::whereIn('item_version_id', $this->versions()->pluck('id'))->sum('quantity');
    }

    /**
     * حد هشدار کالا = بیشترین حد هشدار ورژن‌هایش.
     *
     * حد هشدار روی ورژن تعریف می‌شود ولی این ستون در سطح کالاست؛ سخت‌گیرانه‌ترین
     * حد ملاک قرار می‌گیرد تا هشدار زودتر بیاید، نه دیرتر.
     */
    public function minStock(): float
    {
        return (float) ($this->relationLoaded('versions')
            ? $this->versions->max('min_stock')
            : $this->versions()->max('min_stock')) ?: 0.0;
    }

    /**
     * وضعیت موجودی برای دایره رنگی ستون «وضعیت».
     *
     * قرمز: صفر · زرد: روی حد هشدار یا کمتر · سبز: بالاتر از حد هشدار.
     * کالایی که حد هشدار ندارد، تا وقتی موجودی داشته باشد سبز است.
     */
    public function stockStatus(): string
    {
        $stock = $this->stock_total ?? $this->totalStock();

        if ($stock <= 0) {
            return self::STATUS_OUT;
        }

        $min = $this->minStock();

        return $min > 0 && $stock <= $min ? self::STATUS_LOW : self::STATUS_OK;
    }
}
