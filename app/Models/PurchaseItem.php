<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id', 'item_version_id', 'quantity', 'fx_unit_price', 'weight_kg',
        'unit_price_irr', 'allocated_cost', 'landed_unit_cost',
    ];

    protected $attributes = [
        'unit_price_irr'   => 0,
        'allocated_cost'   => 0,
        'landed_unit_cost' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'decimal:2',
            'fx_unit_price' => 'decimal:4',
            'weight_kg'     => 'decimal:3',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function itemVersion(): BelongsTo
    {
        return $this->belongsTo(ItemVersion::class);
    }

    public function lot(): HasOne
    {
        return $this->hasOne(StockLot::class);
    }
}
