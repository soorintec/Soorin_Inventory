<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سند حرکت انبار — هرگز حذف یا ویرایش نمی‌شود؛ اصلاح فقط با سند معکوس.
 * هر ورود/خروج واقعی یک ردیف اینجا دارد، همراه با کاربر ثبت‌کننده
 * (الزام گزارش «گردش انبار به تفکیک کاربر»).
 */
class StockMovement extends Model
{
    use HasFactory;

    public const DIRECTION_IN  = 'in';
    public const DIRECTION_OUT = 'out';

    public const REASON_PURCHASE  = 'purchase';
    public const REASON_PROJECT   = 'project';
    public const REASON_TICKET    = 'ticket';
    public const REASON_RETURN    = 'return';
    public const REASON_TRANSFER  = 'transfer';
    public const REASON_ADJUSTMENT = 'adjustment';
    public const REASON_INITIAL   = 'initial';
    public const REASON_SCRAP     = 'scrap';

    protected $fillable = [
        'item_version_id', 'warehouse_id', 'stock_lot_id', 'direction', 'reason',
        'quantity', 'unit_cost', 'reference_type', 'reference_id', 'user_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity'  => 'decimal:2',
            'unit_cost' => 'integer',
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

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** جهت‌دار: خروج به‌صورت عدد منفی — برای جمع‌زدن ساده در گزارش. */
    public function signedQuantity(): float
    {
        return $this->direction === self::DIRECTION_OUT ? -abs((float) $this->quantity) : abs((float) $this->quantity);
    }
}
