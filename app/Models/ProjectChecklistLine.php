<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectChecklistLine extends Model
{
    use HasFactory;

    public const STATUS_PENDING         = 'pending';
    public const STATUS_RESERVED        = 'reserved';
    public const STATUS_ISSUED          = 'issued';
    public const STATUS_PURCHASE_NEEDED = 'purchase_needed';

    protected $fillable = [
        'project_id', 'item_id', 'item_version_id',
        'quantity_required', 'quantity_reserved', 'quantity_issued', 'quantity_shortage', 'status',
    ];

    protected $attributes = [
        'quantity_required' => 0,
        'quantity_reserved' => 0,
        'quantity_issued'   => 0,
        'quantity_shortage' => 0,
        'status'            => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:2',
            'quantity_reserved' => 'decimal:2',
            'quantity_issued'   => 'decimal:2',
            'quantity_shortage' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function itemVersion(): BelongsTo
    {
        return $this->belongsTo(ItemVersion::class);
    }
}
