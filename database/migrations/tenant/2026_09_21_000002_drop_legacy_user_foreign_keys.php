<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * حذفِ کلیدهای خارجیِ قدیمیِ جدول‌های tenant به جدولِ مرکزیِ users.
     *
     * در چند-کسب‌وکاری، جدول‌های tenant (دیتابیس/پیشوندِ جدا) نمی‌توانند به جدولِ
     * مرکزیِ users کلیدِ خارجیِ واقعی داشته باشند. ستون‌ها می‌مانند (مرجعِ نرم)، فقط
     * قیدِ FK برداشته می‌شود. برای نصب‌های موجود لازم است؛ برای کسب‌وکارهای تازه که
     * از ابتدا بدونِ FK ساخته شده‌اند، بی‌اثر است (هر drop در try/catch است).
     */
    public function up(): void
    {
        $this->dropFk('stock_movements', 'user_id');
        $this->dropFk('purchases', 'created_by');
        $this->dropFk('projects', 'created_by');
        $this->dropFk('stocktakes', 'started_by');
        $this->dropFk('stocktakes', 'closed_by');
        $this->dropFk('stocktakes', 'applied_by');
        $this->dropFk('activity_logs', 'user_id');
    }

    public function down(): void
    {
        // برگشت‌پذیر نیست: FKهای cross-tenant عمداً بازگردانده نمی‌شوند.
    }

    private function dropFk(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // FK از قبل نبوده (کسب‌وکارِ تازه یا نصبِ بدونِ این قید) — نادیده گرفته می‌شود.
        }
    }
};
