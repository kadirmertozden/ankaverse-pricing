<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ZATEN VARSA OLUŞTURMA
        if (Schema::hasTable('pricing_profiles')) {
            return;
        }

        Schema::create('pricing_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->decimal('min_margin', 5, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->decimal('vat_percent', 5, 2)->default(20);
            $table->string('currency', 8)->default('TRY');
            $table->decimal('rounding', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // DROP öncesi kontrol
        if (Schema::hasTable('pricing_profiles')) {
            Schema::dropIfExists('pricing_profiles');
        }
    }
};
