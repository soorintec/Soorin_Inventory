<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * یادداشت انباردار روی ورژن — «دو عدد برای تعمیر در اختیار مهندس»،
     * «یک عدد معیوب»، «در حال اتمام».
     *
     * در فایل اکسل شرکت ۹۳ سطر چنین توضیحی داشتند. اول فقط روی سند ورود
     * انبار ثبت می‌شد، ولی آنجا کسی دنبالشان نمی‌گردد؛ جای درستشان کنار خود
     * ورژن است. روی سند هم می‌ماند، چون سند سابقه لحظه شمارش است.
     */
    public function up(): void
    {
        Schema::table('item_versions', function (Blueprint $t) {
            $t->text('notes')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('item_versions', function (Blueprint $t) {
            $t->dropColumn('notes');
        });
    }
};
