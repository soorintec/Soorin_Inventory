<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * لات — هر ورود به انبار (خرید یا ثبت موجودی اولیه) یک لات با قیمت
 * تمام‌شده خودش می‌سازد. خروج انبار به روش FIFO از قدیمی‌ترین لات با
 * موجودی باقیمانده مصرف می‌شود. مبنای محاسبه «این سامانه با قیمت خرید
 * همان روز چقدر تمام شد».
 */
class StockLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_version_id', 'warehouse_id', 'purchase_item_id', 'lot_code',
        'received_at', 'quantity_in', 'quantity_remaining', 'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'received_at'         => 'date',
            'quantity_in'         => 'decimal:2',
            'quantity_remaining'  => 'decimal:2',
            'unit_cost'           => 'integer',
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

    public function isExhausted(): bool
    {
        return (float) $this->quantity_remaining <= 0;
    }
}
