<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** یک سطر شمارش انبارگردانی: موجودی سامانه در برابر شمارش واقعی. */
class StocktakeLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'stocktake_id', 'item_version_id', 'system_quantity', 'counted_quantity', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity'  => 'decimal:2',
            'counted_quantity' => 'decimal:2',
        ];
    }

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class);
    }

    public function itemVersion(): BelongsTo
    {
        return $this->belongsTo(ItemVersion::class);
    }

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    /**
     * اختلاف شمارش با سامانه.
     *
     * مثبت = اضافه (بیشتر از دفتر پیدا شد)، منفی = کسری.
     * سطر شمرده‌نشده اختلاف ندارد، صفر برنمی‌گرداند — «نشمرده» با «برابر»
     * یکی نیست.
     */
    public function difference(): ?float
    {
        if (! $this->isCounted()) {
            return null;
        }

        return (float) $this->counted_quantity - (float) $this->system_quantity;
    }

    public function hasDiscrepancy(): bool
    {
        $difference = $this->difference();

        return $difference !== null && abs($difference) > 0.0001;
    }
}
