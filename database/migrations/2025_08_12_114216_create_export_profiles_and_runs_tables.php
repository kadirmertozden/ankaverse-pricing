<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('export_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $t->decimal('min_margin',5,2)->default(25.00);         // %
            $t->decimal('commission_percent',5,2)->default(10.00); // %
            $t->decimal('vat_percent',5,2)->default(20.00);        // %
            $t->decimal('rounding',5,2)->nullable();               // 0.99 gibi
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('export_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('export_profile_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('queued'); // queued|running|done|failed
            $t->string('path')->nullable();          // storage path
            $t->unsignedInteger('product_count')->default(0);
            $t->text('error')->nullable();
            $t->timestamps();

            $t->index(['export_profile_id','status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('export_runs');
        Schema::dropIfExists('export_profiles');
    }
};
