<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * نسخه ساده مشتری — فقط برای مالکیت سامانه اجراشده، پروژه و انبار امانی.
 * جزئیات کامل (تیکت، فاکتور، پرتال) در سامانه پشتیبانی است؛ اینجا هیچ
 * اتصالی به آن سامانه نیست. ستون code عمداً با کد مشتری در آن سامانه
 * یکسان نگه داشته می‌شود تا تطبیق دستی هنگام گزارش‌گیری ممکن باشد.
 */
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'entity_type', 'phone', 'mobile', 'email',
        'city', 'address', 'notes', 'is_active',
    ];

    protected $attributes = [
        'entity_type' => 'company',
        'is_active'   => true,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function customerSystems(): HasMany
    {
        return $this->hasMany(CustomerSystem::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
