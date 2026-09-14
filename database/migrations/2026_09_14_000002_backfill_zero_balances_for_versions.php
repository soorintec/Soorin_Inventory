<?php

use App\Models\ItemVersion;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * برای هر ورژنِ کالا که هیچ ردیفِ موجودی ندارد، یک ردیفِ صفر در انبارِ
     * پیش‌فرض می‌سازد تا در «مدیریت انبار» هم دیده شود (رفعِ باگِ ناپدیدیِ
     * کالای تازه/بی‌موجودی). موجودیِ چیزی عوض نمی‌شود؛ فقط ردیفِ صفر افزوده می‌شود.
     */
    public function up(): void
    {
        $warehouse = Warehouse::where('is_active', true)
            ->orderByRaw("CASE WHEN code = 'MAIN' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        if ($warehouse === null) {
            return; // هنوز انباری نیست
        }

        ItemVersion::whereDoesntHave('balances')->chunkById(200, function ($versions) use ($warehouse) {
            foreach ($versions as $version) {
                StockBalance::firstOrCreate([
                    'item_version_id' => $version->id,
                    'warehouse_id'    => $warehouse->id,
                ]);
            }
        });
    }

    public function down(): void
    {
        // برگشت‌پذیر نیست: نمی‌دانیم کدام ردیفِ صفر را همین مهاجرت ساخته؛
        // پاک‌کردنِ ردیف‌های صفر می‌تواند دادهٔ واقعی را ببرد، پس دست نمی‌زنیم.
    }
};
