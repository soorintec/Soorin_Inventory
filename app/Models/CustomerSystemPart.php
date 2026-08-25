<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قطعه واقعی نصب‌شده در یک سامانه اجراشده. unit_cost از لات FIFO همان
 * زمان مصرف می‌آید و منجمد می‌شود — تغییر قیمت بعدی کالا این را عوض نمی‌کند.
 *
 * replaced_by_ticket_number فقط متن است — این سامانه به سامانه پشتیبانی
 * (که تیکت آنجاست) هیچ اتصال دیتابیسی ندارد.
 */
class CustomerSystemPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_system_id', 'item_version_id', 'item_serial_id', 'quantity', 'unit_cost',
        'installed_at', 'replaced_at', 'replaced_by_ticket_number', 'specs',
    ];

    protected $attributes = ['quantity' => 1, 'unit_cost' => 0];

    protected function casts(): array
    {
        return [
            'quantity'     => 'decimal:2',
            'unit_cost'    => 'integer',
            'installed_at' => 'date',
            'replaced_at'  => 'date',
            'specs'        => 'array',
        ];
    }

    public function customerSystem(): BelongsTo
    {
        return $this->belongsTo(CustomerSystem::class);
    }

    public function itemVersion(): BelongsTo
    {
        return $this->belongsTo(ItemVersion::class);
    }

    public function itemSerial(): BelongsTo
    {
        return $this->belongsTo(ItemSerial::class);
    }
}
