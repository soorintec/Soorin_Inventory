<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نسخه مدل سامانه — مثال: Titan S2 نسخه ۱۴۰۴. لیست قطعات استاندارد
 * (BOM) همان سال. کیس ویز امسال ASUS 4060، سال بعد 5060 → دو نسخه.
 */
class SystemVersion extends Model
{
    use HasFactory;

    protected $fillable = ['system_model_id', 'version_code', 'year', 'notes', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function systemModel(): BelongsTo
    {
        return $this->belongsTo(SystemModel::class);
    }

    public function bomLines(): HasMany
    {
        return $this->hasMany(SystemBomLine::class);
    }

    public function displayName(): string
    {
        return $this->systemModel->name . ' — ' . $this->version_code;
    }

    /**
     * قیمت تمام‌شده تخمینی ساخت یک دستگاه از این نسخه، بر اساس قیمت لات‌های
     * موجود انبار. قطعات اختیاری شمرده نمی‌شوند چون در همه دستگاه‌ها نیستند.
     *
     * «تخمینی» است، نه قطعی: قیمت واقعی لحظه مصرف روی
     * customer_system_parts.unit_cost منجمد می‌شود.
     */
    public function estimatedCost(): int
    {
        return (int) $this->bomLines
            ->reject(fn (SystemBomLine $line) => $line->is_optional)
            ->sum(fn (SystemBomLine $line) => $line->lineCost());
    }

    /** تعداد قطعات الزامی که برای ساخت یک دستگاه موجودی کافی ندارند. */
    public function shortageCount(): int
    {
        return $this->bomLines
            ->reject(fn (SystemBomLine $line) => $line->is_optional)
            ->filter(fn (SystemBomLine $line) => $line->shortage() > 0)
            ->count();
    }

    /**
     * با موجودی فعلی انبار، چند دستگاه کامل از این نسخه می‌توان ساخت؟
     * محدودکننده، کم‌موجودترین قطعه الزامی است.
     */
    public function buildableUnits(): int
    {
        $required = $this->bomLines->reject(fn (SystemBomLine $line) => $line->is_optional);

        if ($required->isEmpty()) {
            return 0;
        }

        return (int) $required
            ->map(function (SystemBomLine $line) {
                $need = (float) $line->quantity;

                return $need > 0 ? floor($line->currentStock() / $need) : 0;
            })
            ->min();
    }
}
