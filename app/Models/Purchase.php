<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * سند خرید/واردات. تا وضعیت «دریافت‌شده» نشود، هیچ اثری روی موجودی
 * ندارد — قیمت تمام‌شده (سرشکن + FIFO) فقط هنگام دریافت محاسبه و
 * لات‌ها ساخته می‌شوند (App\Actions\ReceivePurchase).
 */
class Purchase extends Model
{
    use HasFactory;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ORDERED  = 'ordered';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const ALLOCATION_VALUE    = 'value';
    public const ALLOCATION_WEIGHT   = 'weight';
    public const ALLOCATION_QUANTITY = 'quantity';

    protected $fillable = [
        'number', 'supplier_id', 'warehouse_id', 'order_date', 'received_date', 'type',
        'currency_id', 'fx_amount', 'transfer_date', 'rate_to_irr', 'usd_rate_irr',
        'shipping_cost', 'customs_cost', 'clearance_cost', 'insurance_cost', 'other_cost',
        'allocation_method', 'goods_value_irr', 'total_cost_irr', 'status', 'created_by', 'notes',
    ];

    protected $attributes = [
        'type'              => 'import',
        'allocation_method' => self::ALLOCATION_VALUE,
        'status'            => self::STATUS_DRAFT,
        'fx_amount'         => 0,
        'rate_to_irr'       => 0,
        'usd_rate_irr'      => 0,
        'shipping_cost'     => 0,
        'customs_cost'      => 0,
        'clearance_cost'    => 0,
        'insurance_cost'    => 0,
        'other_cost'        => 0,
        'goods_value_irr'   => 0,
        'total_cost_irr'    => 0,
    ];

    protected function casts(): array
    {
        return [
            'order_date'    => 'date',
            'received_date' => 'date',
            'transfer_date' => 'date',
            'fx_amount'     => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** جمع هزینه‌های جانبی که باید سرشکن شود. */
    public function peripheralCosts(): int
    {
        return (int) $this->shipping_cost + (int) $this->customs_cost
            + (int) $this->clearance_cost + (int) $this->insurance_cost + (int) $this->other_cost;
    }
}
