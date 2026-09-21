<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| جدول‌های پایه سامانه انبار و پروژه.
|
| این سامانه پرتال مشتری ندارد — فقط کاربران داخلی شرکت (مدیر و کارشناس انبار)
| به آن وارد می‌شوند. بنابراین نقش «مشتری» و ستون customer_id در users وجود ندارد.
*/

return new class extends Migration
{
    public function up(): void
    {
        // تنظیمات عمومی سامانه
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('group')->default('general'); // general | inventory | branding
            $t->string('type')->default('string');   // string | text | bool | int | file
            $t->timestamps();
        });

        // کاربران داخلی: مدیر و کارشناس انبار
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique()->nullable();
            $t->string('mobile', 20)->unique()->nullable();
            $t->string('password');
            $t->enum('user_type', ['admin', 'staff'])->default('staff');
            $t->string('theme', 20)->default('ocean'); // ocean | night
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        // نکته: جدولِ activity_logs از این مهاجرتِ مرکزی خارج شد و به مهاجرتِ tenant
        // منتقل شد (سیاههٔ هر کسب‌وکار جداست). نصب‌های موجود که این جدول را دارند،
        // در مهاجرتِ tenant با hasTable رد می‌شوند.
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('settings');
    }
};
