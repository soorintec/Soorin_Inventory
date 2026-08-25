<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدا کردن «پایان انبارگردانی» از «به‌روزرسانی انبار».
     *
     * پیش از این بستن انبارگردانی هم‌زمان موجودی را هم اصلاح می‌کرد. حالا
     * پایان‌دادن فقط شمارش را می‌بندد؛ اعمالِ مغایرت‌ها روی موجودی یک کار
     * جداست که ممکن است اصلاً انجام نشود (شاید فقط برای گزارش انبارگردانی
     * گرفته باشیم). این دو ستون می‌گویند آیا و کِی موجودی به‌روزرسانی شد.
     *
     * عمداً وضعیت جدیدی به enum اضافه نشد تا نیازی به تغییر ساختار ستون نباشد؛
     * «به‌روزشده» یعنی applied_at پر است.
     */
    public function up(): void
    {
        Schema::table('stocktakes', function (Blueprint $t) {
            $t->foreignId('applied_by')->nullable()->after('closed_by')->constrained('users')->nullOnDelete();
            $t->timestamp('applied_at')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('stocktakes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('applied_by');
            $t->dropColumn('applied_at');
        });
    }
};
