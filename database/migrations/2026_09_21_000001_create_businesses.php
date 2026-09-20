<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * رجیستریِ کسب‌وکارها (چند-کسب‌وکاری) — جدول‌های مرکزی روی اتصالِ پیش‌فرض.
     *
     * یک کسب‌وکارِ «اصلی» (پیش‌فرض) با پیشوندِ خالی ساخته می‌شود که به همان دیتابیسِ
     * فعلیِ نصب اشاره می‌کند؛ پس نصبِ تک‌کسب‌وکاریِ موجود بدونِ هیچ جابه‌جاییِ داده به
     * این مدل مهاجرت می‌کند و همهٔ کاربرانِ فعلی به آن وصل می‌شوند.
     */
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('database')->nullable();     // حالتِ database
            $table->string('table_prefix')->nullable(); // حالتِ prefix
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('business_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });

        // کسب‌وکارِ اصلی = همین دیتابیسِ فعلی (پیشوندِ خالی، database=null).
        $business = Business::create([
            'name'         => 'کسب‌وکار اصلی',
            'code'         => 'MAIN',
            'database'     => null,
            'table_prefix' => '',
            'is_active'    => true,
            'is_default'   => true,
        ]);

        // همهٔ کاربرانِ موجود به کسب‌وکارِ اصلی دسترسی داشته باشند.
        if (Schema::hasTable('users')) {
            User::query()->pluck('id')->each(function ($userId) use ($business) {
                DB::table('business_user')->insert([
                    'business_id' => $business->id,
                    'user_id'     => $userId,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_user');
        Schema::dropIfExists('businesses');
    }
};
