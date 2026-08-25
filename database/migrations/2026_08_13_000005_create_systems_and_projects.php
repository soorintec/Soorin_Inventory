<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مدل سامانه: Titan S2
        Schema::create('system_models', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');                 // Titan S2
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // نسخه مدل: Titan S2 — نسخه ۱۴۰۴ (لیست قطعات استاندارد همان سال)
        Schema::create('system_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('system_model_id')->constrained()->cascadeOnDelete();
            $t->string('version_code', 40);     // 1404 | v2
            $t->unsignedSmallInteger('year')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['system_model_id', 'version_code']);
        });

        // BOM — لیست قطعات استاندارد یک نسخه
        Schema::create('system_bom_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('system_version_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained();                                  // ترک‌بال کوچک
            $t->foreignId('item_version_id')->nullable()->constrained()->nullOnDelete(); // ورژن پیش‌فرض
            $t->decimal('quantity', 12, 2)->default(1);
            $t->boolean('is_optional')->default(false);
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        // پروژه اجرا برای یک مشتری
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('title');
            $t->foreignId('customer_id')->constrained();
            $t->foreignId('system_version_id')->nullable()->constrained()->nullOnDelete();
            $t->date('start_date')->nullable();
            $t->date('delivery_date')->nullable();
            $t->enum('status', ['draft', 'planning', 'in_progress', 'delivered', 'cancelled'])->default('draft');
            $t->unsignedBigInteger('total_cost')->default(0);   // قیمت تمام‌شده محاسبه‌شده
            $t->unsignedBigInteger('sale_price')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        // چک‌لیست پروژه: از BOM ساخته می‌شود، موجودی چک و رزرو می‌شود
        Schema::create('project_checklist_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained();
            $t->foreignId('item_version_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('quantity_required', 12, 2)->default(0);
            $t->decimal('quantity_reserved', 12, 2)->default(0);
            $t->decimal('quantity_issued', 12, 2)->default(0);   // از انبار خارج شد
            $t->decimal('quantity_shortage', 12, 2)->default(0); // باید خریداری شود
            $t->enum('status', ['pending', 'reserved', 'issued', 'purchase_needed'])->default('pending');
            $t->timestamps();
        });

        // سامانه اجراشده نزد مشتری — با قطعات واقعی و قیمت همان روز
        Schema::create('customer_systems', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->foreignId('customer_id')->constrained();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('system_version_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');                                   // تایتان S2 — سالن کنترل
            $t->string('location')->nullable();
            $t->date('installed_at')->nullable();
            $t->date('warranty_until')->nullable();
            $t->unsignedBigInteger('total_cost')->default(0);     // قیمت تمام‌شده در زمان اجرا
            $t->enum('status', ['active', 'decommissioned'])->default('active');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        // قطعات واقعی نصب‌شده در آن سامانه
        Schema::create('customer_system_parts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_system_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_version_id')->constrained();
            $t->foreignId('item_serial_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('quantity', 12, 2)->default(1);
            $t->unsignedBigInteger('unit_cost')->default(0);   // از لات FIFO همان زمان
            $t->date('installed_at')->nullable();
            $t->date('replaced_at')->nullable();               // اگر بعداً تعویض شد
            // شماره تیکت در سامانه پشتیبانی — به‌صورت متن ثبت می‌شود.
            // دو سامانه جدا هستند و اتصال دیتابیسی بین آن‌ها وجود ندارد.
            $t->string('replaced_by_ticket_number', 20)->nullable();
            $t->json('specs')->nullable();                     // مشخصات واقعی همان دستگاه نصب‌شده
            $t->timestamps();
        });

        Schema::table('item_serials', function (Blueprint $t) {
            $t->foreign('customer_system_id')->references('id')->on('customer_systems')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('item_serials', fn (Blueprint $t) => $t->dropForeign(['customer_system_id']));
        Schema::dropIfExists('customer_system_parts');
        Schema::dropIfExists('customer_systems');
        Schema::dropIfExists('project_checklist_lines');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('system_bom_lines');
        Schema::dropIfExists('system_versions');
        Schema::dropIfExists('system_models');
    }
};
