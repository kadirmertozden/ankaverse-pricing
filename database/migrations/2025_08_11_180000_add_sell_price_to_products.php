<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $t) {
            if (!Schema::hasColumn('products', 'sell_price')) {
                $t->decimal('sell_price', 12, 2)->nullable()->after('buy_price_vat');
            }
        });
    }
    public function down(): void {
        Schema::table('products', function (Blueprint $t) {
            if (Schema::hasColumn('products', 'sell_price')) {
                $t->dropColumn('sell_price');
            }
        });
    }
};
