<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * محل فیزیکی قفسه در انبار — «D3/#04»، «کشوی راست میانی»، «کمد اتاق».
     * روی ورژن می‌نشیند نه روی کالا، چون در فایل انبار موجود دیده شد که دو
     * ورژن از یک کالا در دو قفسه جدا نگهداری می‌شوند (مثلاً «کابل VGA»).
     */
    public function up(): void
    {
        Schema::table('item_versions', function (Blueprint $t) {
            $t->string('location', 60)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('item_versions', function (Blueprint $t) {
            $t->dropColumn('location');
        });
    }
};
