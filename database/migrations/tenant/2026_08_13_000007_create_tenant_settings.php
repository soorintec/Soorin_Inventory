<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدولِ settingsِ per-business (برای برندینگِ هر کسب‌وکار).
     *
     * برای کسب‌وکارِ اصلی، جدولِ settings از قبل (مهاجرتِ مرکزی) وجود دارد و همان
     * استفاده می‌شود — پس با hasTable رد می‌شود. برای کسب‌وکارِ تازه، جدولِ
     * settingsِ خودش (با پیشوند/در دیتابیسِ جدا) ساخته می‌شود تا برندینگِ مستقل داشته باشد.
     *
     * تنظیماتِ مرکزی (لایسنس/به‌روزرسانی/SSL/زمان‌بندیِ بکاپ) همچنان در settingsِ مرکزی است.
     */
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('group')->default('general');
            $t->string('type')->default('string');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        // جدولِ مرکزی را حذف نمی‌کنیم؛ فقط برای کسب‌وکارهای تازه معنا دارد.
    }
};
