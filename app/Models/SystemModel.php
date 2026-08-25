<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * مدل سامانه — نقشه (مثال: Titan S2). این «نقشه» است، نه سامانه واقعی
 * نصب‌شده. تغییر مدل نباید سوابق نصب‌شده گذشته را عوض کند.
 */
class SystemModel extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SystemVersion::class);
    }

    /** همه قطعات همه نسخه‌ها — برای شمردن در فهرست مدل‌ها. */
    public function bomLines(): HasManyThrough
    {
        return $this->hasManyThrough(SystemBomLine::class, SystemVersion::class);
    }
}
