<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * حذف نرم برای کالا، ورژن و دسته‌بندی.
     *
     * حذف واقعی کالا شکست می‌خورد چون stock_movements کلید خارجی محافظ دارد و
     * عمداً نمی‌گذارد تاریخچه انبار نابود شود (قاعده ۳ پروژه). راه درست همان
     * قاعده ۶ است: همه‌جا حذف نرم.
     *
     * با این کار کالا از فهرست‌ها ناپدید می‌شود ولی سند تراکنش، لات و قیمت
     * تمام‌شده گذشته سر جایشان می‌مانند.
     */
    public function up(): void
    {
        foreach (['items', 'item_versions', 'item_categories'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['items', 'item_versions', 'item_categories'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
