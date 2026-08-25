<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** یک ردیف از لیست قطعات استاندارد (BOM) یک نسخه سامانه. */
class SystemBomLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'system_version_id', 'item_id', 'item_version_id', 'quantity', 'is_optional', 'notes',
    ];

    protected $attributes = ['quantity' => 1, 'is_optional' => false];

    protected function casts(): array
    {
        return [
            'quantity'    => 'decimal:2',
            'is_optional' => 'boolean',
        ];
    }

    public function systemVersion(): BelongsTo
    {
        return $this->belongsTo(SystemVersion::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function itemVersion(): BelongsTo
    {
        return $this->belongsTo(ItemVersion::class);
    }

    /**
     * موجودی فعلی این قطعه در همه انبارها.
     *
     * اگر ردیف BOM ورژن مشخصی را الزام کرده باشد، فقط همان ورژن شمرده می‌شود؛
     * وگرنه هر ورژنی از آن کالا قابل استفاده است و همه با هم شمرده می‌شوند.
     */
    public function currentStock(): float
    {
        if ($this->item_version_id) {
            return (float) StockBalance::where('item_version_id', $this->item_version_id)->sum('quantity');
        }

        return $this->item?->totalStock() ?? 0.0;
    }

    /** چند تا کم داریم؟ صفر یعنی برای ساخت یک دستگاه موجودی کافی است. */
    public function shortage(): float
    {
        return max(0.0, (float) $this->quantity - $this->currentStock());
    }

    /**
     * قیمت تمام‌شده واحد از قدیمی‌ترین لات باقی‌مانده — یعنی دقیقاً همان لاتی
     * که هنگام ساخت واقعی، FIFO مصرفش می‌کند. صفر یعنی هنوز خریدی با قیمت
     * ثبت نشده (مثل کالاهای واردشده از فایل اکسل).
     */
    public function unitCost(): int
    {
        $lots = StockLot::where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id');

        if ($this->item_version_id) {
            $lots->where('item_version_id', $this->item_version_id);
        } else {
            $lots->whereIn('item_version_id', ItemVersion::where('item_id', $this->item_id)->select('id'));
        }

        return (int) ($lots->value('unit_cost') ?? 0);
    }

    /** قیمت کل این ردیف = قیمت واحد × تعداد موردنیاز. */
    public function lineCost(): int
    {
        return (int) round($this->unitCost() * (float) $this->quantity);
    }
}
