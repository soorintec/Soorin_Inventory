<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * قیمت ارزی کالاهای وارداتی.
     *
     * روی ورژن می‌نشیند نه روی کالا، چون هر ورژن خرید جداگانه‌ای است و قیمت
     * ارزی‌اش هم فرق می‌کند — همان قاعده‌ای که موجودی و قیمت ریالی را هم روی
     * ورژن نگه می‌دارد.
     *
     * ارز به‌صورت decimal ذخیره می‌شود (نه عدد صحیح مثل ریال)، چون قیمت ارزی
     * اعشار واقعی دارد: ۱۲٫۵۰ دلار. تأمین‌کننده شرکت چینی است، پس واحد ارز
     * ثابت USD فرض نمی‌شود.
     */
    public function up(): void
    {
        Schema::table('item_versions', function (Blueprint $t) {
            $t->decimal('fx_price', 14, 2)->nullable()->after('notes');
            $t->string('fx_currency', 3)->nullable()->after('fx_price');
        });
    }

    public function down(): void
    {
        Schema::table('item_versions', function (Blueprint $t) {
            $t->dropColumn(['fx_price', 'fx_currency']);
        });
    }
};
