<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ساختار سه‌سطحی: دسته ← کالا ← ورژن
        Schema::create('item_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('item_categories')->cascadeOnDelete();
            $t->string('name', 100);             // مانیتور | ترک‌بال | کیس
            $t->string('code', 20)->nullable();
            // قالب مشخصات فنی این دسته: [{key:"cpu",label:"پردازنده",type:"string"}, ...]
            $t->json('spec_template')->nullable();
            $t->timestamps();
        });

        Schema::create('items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_category_id')->constrained();
            $t->string('code', 30)->unique();    // کد کالا — مبنای اتصال به انبار خارجی
            $t->string('name');                  // ترک‌بال کوچک
            $t->string('brand', 60)->nullable();
            $t->string('unit', 20)->default('عدد');
            $t->boolean('track_serial')->default(false);  // اقلام گران: سریال ثبت شود
            $t->boolean('is_active')->default(true);
            $t->text('description')->nullable();
            $t->timestamps();
        });

        // موجودی و قیمت همیشه روی ورژن ثبت می‌شود، نه روی کالا — تصمیم کلیدی
        Schema::create('item_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')->constrained()->cascadeOnDelete();
            $t->string('version_code', 40);      // ۴۰۴ | ۴۰۵ | 2026-A
            $t->string('name')->nullable();      // نام نمایشی اختیاری
            $t->unsignedSmallInteger('year')->nullable();  // سال خرید/تولید (شمسی)
            $t->json('specs')->nullable();       // مقادیر بر اساس spec_template دسته
            $t->unsignedInteger('min_stock')->default(0);  // حد هشدار موجودی
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['item_id', 'version_code']);
        });

        // چند انبار: مرکزی | امانی نزد مشتری | مرجوعی و معیوب
        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('code', 20)->unique();
            $t->enum('type', ['main', 'consignment', 'defective', 'transit'])->default('main');
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); // برای انبار امانی
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // لات: هر خرید یک لات با قیمت تمام‌شده مخصوص خودش — مبنای FIFO
        Schema::create('stock_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_version_id')->constrained()->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained();
            $t->unsignedBigInteger('purchase_item_id')->nullable()->index(); // مرجع سند خرید
            $t->string('lot_code', 40)->nullable();
            $t->date('received_at');
            $t->decimal('quantity_in', 14, 2)->default(0);
            $t->decimal('quantity_remaining', 14, 2)->default(0);
            $t->unsignedBigInteger('unit_cost')->default(0);   // قیمت تمام‌شده هر واحد (ریال)
            $t->timestamps();
            $t->index(['item_version_id', 'warehouse_id']);
        });

        // هر حرکت انبار با کاربر ثبت‌کننده — الزام کاربر برای گزارش انبار
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_version_id')->constrained();
            $t->foreignId('warehouse_id')->constrained();
            $t->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('direction', ['in', 'out']);
            $t->enum('reason', [
                'purchase', 'project', 'ticket', 'return',
                'transfer', 'adjustment', 'initial', 'scrap',
            ]);
            $t->decimal('quantity', 14, 2);
            $t->unsignedBigInteger('unit_cost')->default(0);
            $t->string('reference_type')->nullable();     // Project | Ticket | Purchase
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();  // چه کسی ثبت کرد
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['reference_type', 'reference_id']);
            $t->index(['item_version_id', 'created_at']);
        });

        // موجودی جاری — برای سرعت گزارش، از روی حرکات به‌روز می‌شود
        Schema::create('stock_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_version_id')->constrained()->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $t->decimal('quantity', 14, 2)->default(0);
            $t->decimal('reserved', 14, 2)->default(0);   // رزروشده برای پروژه‌ها
            $t->timestamps();
            $t->unique(['item_version_id', 'warehouse_id']);
        });

        // سریال اقلام گران + گارانتی تأمین‌کننده
        Schema::create('item_serials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_version_id')->constrained()->cascadeOnDelete();
            $t->string('serial', 80);
            $t->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('customer_system_id')->nullable()->index(); // اگر نصب شده باشد
            $t->date('supplier_warranty_until')->nullable();
            $t->enum('status', ['in_stock', 'installed', 'defective', 'scrapped'])->default('in_stock');
            $t->timestamps();
            $t->unique(['item_version_id', 'serial']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('item_serials');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('item_versions');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
    }
};
