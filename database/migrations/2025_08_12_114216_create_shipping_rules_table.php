<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shipping_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('desi_min',8,2)->nullable();
            $t->decimal('desi_max',8,2)->nullable();
            $t->decimal('weight_min',8,2)->nullable();
            $t->decimal('weight_max',8,2)->nullable();
            $t->decimal('price',10,2); // kargo bedeli
            $t->timestamps();

            $t->index(['marketplace_id','desi_min','desi_max']);
            $t->index(['marketplace_id','weight_min','weight_max']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('shipping_rules');
    }
};
