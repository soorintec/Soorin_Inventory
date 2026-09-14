<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\SystemBomLine;
use App\Models\SystemModel;
use App\Models\SystemVersion;
use Illuminate\Support\Facades\DB;

/**
 * کپیِ مدل سامانه و نسخه‌ها.
 *
 * هدف: برای ساختِ مدل/نسخهٔ تازه که شبیهِ نمونهٔ قبلی است، از صفر شروع نشود؛
 * یک کپیِ کامل (با همهٔ نسخه‌ها و قطعاتشان) ساخته و بعد ویرایش می‌شود.
 *
 * فقط «نقشه» کپی می‌شود (مدل/نسخه/BOM)، نه موجودی یا سامانهٔ اجراشده.
 */
class SystemDuplicationService
{
    /**
     * کپیِ کاملِ یک مدل: همهٔ نسخه‌ها و قطعاتِ هر نسخه.
     *
     * فقط نامِ تازه از کاربر گرفته می‌شود؛ کد به‌صورت خودکار یکتا می‌شود چون
     * ستونِ code یکتاست و نمی‌تواند تکراری باشد.
     */
    public function duplicateModel(SystemModel $model, string $newName): SystemModel
    {
        return DB::transaction(function () use ($model, $newName) {
            $copy = SystemModel::create([
                'code'        => $this->uniqueModelCode($model->code),
                'name'        => $newName,
                'description' => $model->description,
                'is_active'   => $model->is_active,
            ]);

            foreach ($model->versions()->with('bomLines')->get() as $version) {
                $this->cloneVersionInto($copy->id, $version, $version->version_code);
            }

            ActivityLog::record('system_model_duplicated', $copy, [
                'from_id'   => $model->id,
                'from_name' => $model->name,
            ]);

            return $copy;
        });
    }

    /**
     * کپیِ یک نسخه در همان مدل، با همهٔ قطعاتش و یک کد نسخهٔ تازه.
     */
    public function duplicateVersion(SystemVersion $version, string $newVersionCode): SystemVersion
    {
        return DB::transaction(function () use ($version, $newVersionCode) {
            $version->loadMissing('bomLines');

            $copy = $this->cloneVersionInto($version->system_model_id, $version, $newVersionCode);

            ActivityLog::record('system_version_duplicated', $copy, [
                'from_id'      => $version->id,
                'version_code' => $newVersionCode,
            ]);

            return $copy;
        });
    }

    /** ساختِ یک نسخهٔ تازه (کپیِ مشخصات + قطعات) زیرِ مدلِ داده‌شده. */
    private function cloneVersionInto(int $modelId, SystemVersion $version, string $versionCode): SystemVersion
    {
        $copy = SystemVersion::create([
            'system_model_id' => $modelId,
            'version_code'    => $versionCode,
            'year'            => $version->year,
            'notes'           => $version->notes,
            'is_active'       => $version->is_active,
        ]);

        foreach ($version->bomLines as $line) {
            $copy->bomLines()->create([
                'item_id'         => $line->item_id,
                'item_version_id' => $line->item_version_id,
                'quantity'        => $line->quantity,
                'is_optional'     => $line->is_optional,
                'notes'           => $line->notes,
            ]);
        }

        return $copy;
    }

    /**
     * کدِ یکتا برای مدلِ کپی — از کدِ اصلی با پسوندِ COPY (و شماره اگر لازم شد).
     * کد کوتاه نگه داشته می‌شود تا از سقفِ طولِ ستون نگذرد.
     */
    private function uniqueModelCode(string $base): string
    {
        $prefix = mb_substr($base, 0, 20) . '-COPY';
        $candidate = $prefix;
        $n = 1;

        while (SystemModel::where('code', $candidate)->exists()) {
            $n++;
            $candidate = $prefix . $n;
        }

        return $candidate;
    }
}
