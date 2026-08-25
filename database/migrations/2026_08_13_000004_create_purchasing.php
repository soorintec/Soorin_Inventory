<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('country', 60)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('currencies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();   // CNY | USD | AED
            $t->string('name', 40);             // یوان | دلار
            $t->timestamps();
        });

        // سند خرید / واردات
        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('warehouse_id')->constrained();
            $t->date('order_date');
            $t->date('received_date')->nullable();
            $t->enum('type', ['import', 'local'])->default('import');

            // ارز و نرخ روز حواله — الزام کاربر
            $t->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('fx_amount', 16, 2)->default(0);       // مبلغ کل به ارز (مثلاً یوان)
            $t->date('transfer_date')->nullable();             // تاریخ حواله
            $t->unsignedBigInteger('rate_to_irr')->default(0); // نرخ ارز اصلی به ریال در روز حواله
            $t->unsignedBigInteger('usd_rate_irr')->default(0);// نرخ دلار همان روز (برای مرجع)

            // هزینه‌های جانبی که سرشکن می‌شوند
            $t->unsignedBigInteger('shipping_cost')->default(0);  // حمل
            $t->unsignedBigInteger('customs_cost')->default(0);   // گمرک
            $t->unsignedBigInteger('clearance_cost')->default(0); // ترخیص
            $t->unsignedBigInteger('insurance_cost')->default(0); // بیمه
            $t->unsignedBigInteger('other_cost')->default(0);
            $t->enum('allocation_method', ['value', 'weight', 'quantity'])->default('value');

            $t->unsignedBigInteger('goods_value_irr')->default(0); // ارزش کالا به ریال
            $t->unsignedBigInteger('total_cost_irr')->default(0);  // ارزش کالا + همه هزینه‌ها
            $t->enum('status', ['draft', 'ordered', 'received', 'cancelled'])->default('draft');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_version_id')->constrained();
            $t->decimal('quantity', 14, 2);
            $t->decimal('fx_unit_price', 16, 4)->default(0);      // قیمت واحد به ارز
            $t->decimal('weight_kg', 12, 3)->nullable();          // برای سرشکن وزنی
            $t->unsignedBigInteger('unit_price_irr')->default(0); // قیمت واحد به ریال (بدون هزینه جانبی)
            $t->unsignedBigInteger('allocated_cost')->default(0); // سهم این ردیف از هزینه‌های جانبی
            $t->unsignedBigInteger('landed_unit_cost')->default(0); // قیمت تمام‌شده نهایی هر واحد
            $t->timestamps();
        });

        Schema::table('stock_lots', function (Blueprint $t) {
            $t->foreign('purchase_item_id')->references('id')->on('purchase_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_lots', fn (Blueprint $t) => $t->dropForeign(['purchase_item_id']));
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('suppliers');
    }
};
