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

    /**
     * پالتِ نشانِ انبار — فقط نام‌های معناییِ تمِ فیلامنت (نه رنگِ هاردکد)، تا
     * با قاعدهٔ «رنگ‌ها از تم/برندینگ، نه هاردکد» نشکند و در تمِ شب هم درست بنشیند.
     */
    public const BADGE_PALETTE = ['info', 'success', 'warning', 'primary', 'danger', 'gray'];

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

    /**
     * رنگِ نشانِ این انبار در فهرست‌ها — تا وقتی فیلترِ انبار روی «همه» است،
     * هر انبار با رنگی متمایز از بقیه دیده شود.
     */
    public function badgeColor(): string
    {
        return self::colorForName($this->name);
    }

    /**
     * رنگ بر پایهٔ نامِ انبار (نه شناسه) انتخاب می‌شود تا یک انبار در هر دو فهرستِ
     * «موجودی انبار» و «مدیریت انبار» — حتی آنجا که فقط نام در دست است — همان رنگ
     * را بگیرد و کاربر رنگ‌ها را با هم یکی ببیند.
     */
    public static function colorForName(?string $name): string
    {
        if (blank($name)) {
            return 'gray';
        }

        return self::BADGE_PALETTE[crc32($name) % count(self::BADGE_PALETTE)];
    }
}
