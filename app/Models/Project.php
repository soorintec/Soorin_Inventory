<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * پروژه اجرا برای یک مشتری. چک‌لیست از BOM ساخته می‌شود و با موجودی
 * تطبیق داده می‌شود (موجود+رزرو یا کسری).
 */
class Project extends Model
{
    use HasFactory;

    public const STATUS_DRAFT       = 'draft';
    public const STATUS_PLANNING    = 'planning';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DELIVERED   = 'delivered';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'code', 'title', 'customer_id', 'system_version_id', 'start_date', 'delivery_date',
        'status', 'total_cost', 'sale_price', 'created_by', 'notes',
    ];

    protected $attributes = [
        'status'     => self::STATUS_DRAFT,
        'total_cost' => 0,
        'sale_price' => 0,
    ];

    protected function casts(): array
    {
        return [
            'start_date'    => 'date',
            'delivery_date' => 'date',
            'total_cost'    => 'integer',
            'sale_price'    => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function systemVersion(): BelongsTo
    {
        return $this->belongsTo(SystemVersion::class);
    }

    public function checklistLines(): HasMany
    {
        return $this->hasMany(ProjectChecklistLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
