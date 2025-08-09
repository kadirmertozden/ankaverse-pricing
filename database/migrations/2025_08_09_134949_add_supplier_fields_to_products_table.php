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
    Schema::table('products', function (Blueprint $table) {
        $table->string('brand')->nullable()->after('name');
        $table->string('category_path')->nullable()->after('brand');
        $table->integer('stock_amount')->default(0)->after('category_path');
        $table->string('currency_code', 8)->nullable()->after('stock_amount');
        $table->decimal('vat_rate', 5, 2)->nullable()->after('currency_code');
        $table->string('gtin', 32)->nullable()->after('vat_rate');
        $table->decimal('volumetric_weight', 8, 2)->nullable()->after('height');
        $table->json('images')->nullable()->after('gtin');
        $table->text('description')->nullable()->after('images');
    });
}
public function down(): void
{
    Schema::table('products', function (Blueprint $table) {
        $table->dropColumn([
            'brand','category_path','stock_amount','currency_code','vat_rate',
            'gtin','volumetric_weight','images','description'
        ]);
    });
}

};
