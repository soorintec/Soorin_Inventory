<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ورژن کالا — سطح سوم و جایی که موجودی و قیمت واقعاً ثبت می‌شود.
 * مثال: ترک‌بال کوچک ← ورژن ۴۰۴ (موجودی ۸) و ورژن ۴۰۵ (موجودی ۱۲).
 */
class ItemVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_id', 'version_code', 'name', 'location', 'notes', 'fx_price', 'fx_currency',
        'year', 'specs', 'min_stock', 'is_active',
    ];

    protected $attributes = [
        'min_stock'   => 0,
        'is_active'   => true,
        'fx_currency' => 'IRR',
    ];

    protected function casts(): array
    {
        return [
            'specs'     => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function serials(): HasMany
    {
        return $this->hasMany(ItemSerial::class);
    }

    /** موجودی کل این ورژن در همه انبارها. */
    public function totalQuantity(): float
    {
        return (float) $this->balances()->sum('quantity');
    }

    /** آیا موجودی از حد هشدار پایین‌تر است؟ */
    public function isBelowMinStock(): bool
    {
        return $this->min_stock > 0 && $this->totalQuantity() < $this->min_stock;
    }

    /** آیا برای این ورژن قیمتی ثبت شده است؟ */
    public function hasPrice(): bool
    {
        return $this->fx_price !== null && (float) $this->fx_price > 0;
    }

    /** آیا این ورژن وارداتی است؟ ملاک، داشتن قیمتِ ارزی (نه ریالی) است. */
    public function isImported(): bool
    {
        return $this->hasPrice() && $this->fx_currency !== 'IRR';
    }

    /** برچسب فارسی ارز قیمت — از جدول ارز خوانده می‌شود (مثلاً «ریال» یا «روبل»). */
    public function currencyLabel(): string
    {
        return Currency::label($this->fx_currency);
    }

    /** قیمت با ارز، برای نمایش: «۸۵۰٬۰۰۰ ریال» یا «۱۲٫۵ دلار» — یا null اگر قیمتی نباشد. */
    public function fxPriceLabel(): ?string
    {
        if (! $this->hasPrice()) {
            return null;
        }

        return \App\Support\Jalali::quantity($this->fx_price) . ' ' . $this->currencyLabel();
    }

    /** نام نمایشی کامل: «ترک‌بال کوچک — ۴۰۴» */
    public function displayName(): string
    {
        $base = $this->item->name . ' — ' . $this->version_code;

        return $this->name ? "{$base} ({$this->name})" : $base;
    }
}
