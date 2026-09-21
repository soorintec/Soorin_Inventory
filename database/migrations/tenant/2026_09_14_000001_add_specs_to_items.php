<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدولِ مشخصات فنی روی خودِ کالا.
     *
     * پیش از این، قالبِ مشخصات فنی روی «دستهٔ کالا» بود و مقدارها روی ورژن. حالا
     * به‌درخواستِ مالک، مشخصات فنی کالا-محور و اختیاری است: هر کالا یک تیکِ
     * has_specs دارد و در صورتِ روشن‌بودن، جدولِ مشخصات (فهرستی از عنوان/مقدار)
     * در ستونِ specs ذخیره می‌شود. همهٔ کالاها به این جدول نیاز ندارند.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('has_specs')->default(false)->after('track_serial');
            $table->json('specs')->nullable()->after('has_specs');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['has_specs', 'specs']);
        });
    }
};
