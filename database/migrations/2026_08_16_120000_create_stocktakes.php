<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * انبارگردانی.
     *
     * روال طبق خواسته مالک پروژه: به مسئول شمارش، فهرست کالاها با همه جزئیات
     * **به‌جز موجودی** داده می‌شود تا عدد سامانه شمارشش را جهت‌دار نکند. بعد
     * شمارش وارد می‌شود و سامانه مغایرت‌ها را نشان می‌دهد.
     *
     * موجودی سامانه در لحظه شروع روی هر سطر **منجمد** می‌شود
     * (system_quantity)، چون شمارش ممکن است چند ساعت طول بکشد و در این فاصله
     * ورود و خروج ثبت شود؛ بدون انجماد، مغایرت‌ها دروغ درمی‌آیند.
     */
    public function up(): void
    {
        Schema::create('stocktakes', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();          // ANB-1405-01
            $t->foreignId('warehouse_id')->constrained();
            $t->enum('status', ['open', 'counting', 'closed', 'cancelled'])->default('open');
            $t->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('stocktake_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stocktake_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_version_id')->constrained();

            // موجودی سامانه در لحظه شروع — منجمد می‌شود
            $t->decimal('system_quantity', 14, 2)->default(0);

            // شمارش واقعی انباردار؛ null یعنی هنوز شمرده نشده
            $t->decimal('counted_quantity', 14, 2)->nullable();

            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['stocktake_id', 'item_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_lines');
        Schema::dropIfExists('stocktakes');
    }
};
