<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * موجودی جاری — برای سرعت گزارش، از روی حرکات به‌روز نگه داشته می‌شود
 * (نه منبع حقیقت؛ منبع حقیقت مجموع stock_movements است). فقط
 * StockMovementService این جدول را می‌نویسد.
 */
class StockBalance extends Model
{
    use HasFactory;

    protected $fillable = ['item_version_id', 'warehouse_id', 'quantity', 'reserved'];

    protected $attributes = ['quantity' => 0, 'reserved' => 0];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'reserved' => 'decimal:2',
        ];
    }

    public function itemVersion(): BelongsTo
    {
        return $this->belongsTo(ItemVersion::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** موجودی آزاد (غیررزروشده) — آنچه واقعاً قابل تخصیص به پروژه جدید است. */
    public function available(): float
    {
        return max(0, (float) $this->quantity - (float) $this->reserved);
    }
}
