<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * کسب‌وکار (tenant) — یک واحدِ کاریِ مستقل با دیتای جداگانه.
 *
 * این مدل «مرکزی» است (روی اتصالِ پیش‌فرض) و فقط رجیستریِ کسب‌وکارهاست؛ دیتای
 * عملیاتیِ هر کسب‌وکار در دیتابیس/پیشوندِ خودش نگه‌داری می‌شود:
 *   - حالتِ database: ستونِ `database` نامِ دیتابیسِ آن کسب‌وکار.
 *   - حالتِ prefix: ستونِ `table_prefix` پیشوندِ جدول‌های آن کسب‌وکار.
 * کسب‌وکارِ اصلی (پیش‌فرض) پیشوندِ خالی و database=null دارد؛ یعنی همان دیتابیسِ
 * فعلیِ نصب — پس نصب‌های تک‌کسب‌وکاری بدونِ جابه‌جاییِ داده کار می‌کنند.
 */
class Business extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'database', 'table_prefix', 'is_active', 'is_default'];

    protected $attributes = [
        'is_active'  => true,
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /** کاربرانی که به این کسب‌وکار دسترسی دارند. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** کسب‌وکارِ پیش‌فرض (اصلی) — همان دیتابیسِ فعلیِ نصب. */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->orderBy('id')->first();
    }
}
