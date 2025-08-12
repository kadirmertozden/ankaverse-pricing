<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('commission_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $t->string('marketplace_category_id');   // Pazaryeri kategori ID
            $t->decimal('commission_percent',5,2);   // %
            $t->timestamps();

            $t->unique(['marketplace_id','marketplace_category_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('commission_rules');
    }
};
