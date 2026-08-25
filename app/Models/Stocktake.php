<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * یک دوره انبارگردانی روی یک انبار.
 *
 * موجودی سامانه هنگام شروع روی هر سطر منجمد می‌شود تا شمارشی که چند ساعت
 * طول می‌کشد با ورود و خروج همان روز قاطی نشود.
 */
class Stocktake extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN      = 'open';       // ساخته شده، فهرست آماده شمارش
    public const STATUS_COUNTING  = 'counting';   // شمارش شروع شده
    public const STATUS_CLOSED    = 'closed';     // نهایی و ثبت‌شده
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code', 'warehouse_id', 'status', 'started_by', 'closed_by', 'applied_by',
        'started_at', 'closed_at', 'applied_at', 'notes',
    ];

    protected $attributes = ['status' => self::STATUS_OPEN];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'closed_at'  => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StocktakeLine::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /** هنوز در حال شمارش است و می‌توان شمارش را ویرایش کرد. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_COUNTING], true);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /** آیا موجودی انبار با نتیجه این انبارگردانی به‌روز شده است؟ */
    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }

    /** آیا می‌توان الان موجودی را با این انبارگردانی به‌روز کرد؟ */
    public function canApplyToStock(): bool
    {
        return $this->isClosed() && ! $this->isApplied();
    }

    /** سطرهایی که شمارش و سامانه با هم نمی‌خوانند. */
    public function discrepancies(): \Illuminate\Support\Collection
    {
        return $this->lines->filter(fn (StocktakeLine $line) => $line->hasDiscrepancy());
    }

    public function countedLines(): \Illuminate\Support\Collection
    {
        return $this->lines->whereNotNull('counted_quantity');
    }

    /** جمع کسری و اضافه — برای خلاصه بالای گزارش. */
    public function totalShortage(): float
    {
        return (float) $this->lines->sum(fn (StocktakeLine $l) => max(0, -$l->difference()));
    }

    public function totalSurplus(): float
    {
        return (float) $this->lines->sum(fn (StocktakeLine $l) => max(0, $l->difference()));
    }
}
