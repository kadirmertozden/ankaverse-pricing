<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('category_mappings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $t->string('internal_category_path');         // ör: "Ev & Yaşam > Dekorasyon > Biblo"
            $t->string('marketplace_category_id');        // ör: HB kategori ID
            $t->timestamps();

            $t->unique(['marketplace_id','internal_category_path']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('category_mappings');
    }
};
