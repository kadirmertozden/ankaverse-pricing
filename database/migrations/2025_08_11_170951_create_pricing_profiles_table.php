<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pricing_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // Örn: "Yenitoptanci varsayılan"
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('min_margin', 5, 2)->default(0);        // % kâr
            $table->decimal('commission_percent', 5, 2)->default(0);// % komisyon
            $table->decimal('vat_percent', 5, 2)->default(20);      // % KDV
            $table->string('currency', 8)->default('TRY');          // TRY, USD, EUR...
            $table->decimal('rounding', 5, 2)->nullable();          // Örn: 0.99
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_profiles');
    }
};
