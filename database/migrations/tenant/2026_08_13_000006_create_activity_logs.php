<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سیاههٔ تغییرات — جدولِ tenant (سیاههٔ هر کسب‌وکار جداست).
     *
     * پیش‌تر در مهاجرتِ مرکزیِ create_settings_and_users ساخته می‌شد؛ حالا tenant
     * است. برای نصب‌های موجود که این جدول را از قبل دارند، با hasTable رد می‌شود.
     *
     * user_id مرجعِ نرم به users مرکزی است (بدونِ FK)، چون در چند-کسب‌وکاری جدولِ
     * tenant نمی‌تواند به جدولِ مرکزی کلیدِ خارجیِ واقعی داشته باشد.
     */
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('action', 50);
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('changes')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
