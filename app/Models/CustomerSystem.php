<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * سامانه اجراشده نزد مشتری — با قطعات واقعی و قیمت همان روز نصب.
 * این با «مدل سامانه» (نقشه) فرق دارد: نقشه تغییر می‌کند، این سوابق
 * دست‌نخورده می‌ماند. total_cost قیمت تمام‌شده لحظه اجرا (منجمد) است.
 */
class CustomerSystem extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE         = 'active';
    public const STATUS_DECOMMISSIONED = 'decommissioned';

    protected $fillable = [
        'code', 'customer_id', 'project_id', 'system_version_id', 'name', 'location',
        'installed_at', 'warranty_until', 'total_cost', 'status', 'notes',
    ];

    protected $attributes = [
        'status'     => self::STATUS_ACTIVE,
        'total_cost' => 0,
    ];

    protected function casts(): array
    {
        return [
            'installed_at'   => 'date',
            'warranty_until' => 'date',
            'total_cost'     => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function systemVersion(): BelongsTo
    {
        return $this->belongsTo(SystemVersion::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(CustomerSystemPart::class);
    }

    /** قیمت تمام‌شده از جمع قطعات واقعی نصب‌شده. */
    public function recalculateTotalCost(): void
    {
        $total = (int) $this->parts()->get()->sum(fn ($p) => (float) $p->quantity * $p->unit_cost);
        $this->forceFill(['total_cost' => $total])->save();
    }
}
