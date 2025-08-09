<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('stock_code')->unique();
        $table->string('name');
        $table->decimal('buy_price_vat', 10, 2); // KDV dahil alış fiyatı
        $table->integer('commission_rate'); // %
        $table->decimal('width', 8, 2);
        $table->decimal('length', 8, 2);
        $table->decimal('height', 8, 2);
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
	
	
};
