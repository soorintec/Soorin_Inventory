<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سریال اقلام گران + گارانتی تأمین‌کننده. */
class ItemSerial extends Model
{
    use HasFactory;

    public const STATUS_IN_STOCK  = 'in_stock';
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_DEFECTIVE = 'defective';
    public const STATUS_SCRAPPED  = 'scrapped';

    protected $fillable = [
        'item_version_id', 'serial', 'warehouse_id', 'customer_system_id',
        'supplier_warranty_until', 'status',
    ];

    protected $attributes = ['status' => self::STATUS_IN_STOCK];

    protected function casts(): array
    {
        return ['supplier_warranty_until' => 'date'];
    }

    public function itemVersion(): BelongsTo
    {
        return $this->belongsTo(ItemVersion::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customerSystem(): BelongsTo
    {
        return $this->belongsTo(CustomerSystem::class);
    }
}
