<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    public const TYPE_MAIN        = 'main';
    public const TYPE_CONSIGNMENT = 'consignment';
    public const TYPE_DEFECTIVE   = 'defective';
    public const TYPE_TRANSIT     = 'transit';

    protected $fillable = ['name', 'code', 'type', 'customer_id', 'is_active'];

    protected $attributes = [
        'type'      => self::TYPE_MAIN,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }
}
