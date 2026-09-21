<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| نسخه ساده جدول مشتری — مخصوص سامانه انبار و پروژه.
|
| این سامانه از سامانه پشتیبانی (Soorin_Support) کاملاً جداست و هیچ اتصال
| دیتابیسی با آن ندارد. مشتری اینجا فقط برای سه کاربرد لازم است:
|   ۱. تعیین مالک «سامانه اجراشده» (customer_systems)
|   ۲. تعیین مالک پروژه (projects)
|   ۳. انبار امانی نزد مشتری (warehouses)
|
| بنابراین فیلدهای مربوط به پرتال، تیکت و وضعیت خدمات‌دهی اینجا وجود ندارند.
| ستون `code` عمداً با کد مشتری در سامانه پشتیبانی یکسان نگه داشته می‌شود تا
| هنگام گزارش‌گیری دستی، تطبیق دو سامانه ممکن باشد.
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();   // همان کد مشتری در سامانه پشتیبانی
            $t->string('name');
            $t->enum('entity_type', ['person', 'company'])->default('company');
            $t->string('phone', 30)->nullable();
            $t->string('mobile', 20)->nullable();
            $t->string('email')->nullable();
            $t->string('city', 80)->nullable();
            $t->text('address')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
