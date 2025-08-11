<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $t) {
            if (!Schema::hasColumn('products', 'base_cost'))
                $t->decimal('base_cost', 12, 2)->nullable()->after('id');

            if (!Schema::hasColumn('products', 'currency'))
                $t->string('currency', 8)->default('TL');

            if (!Schema::hasColumn('products', 'commission_rate'))
                $t->decimal('commission_rate', 8, 2)->default(0);

            if (!Schema::hasColumn('products', 'vat_rate'))
                $t->decimal('vat_rate', 8, 2)->default(0);

            if (!Schema::hasColumn('products', 'dims'))
                $t->json('dims')->nullable();

            if (!Schema::hasColumn('products', 'images'))
                $t->json('images')->nullable();

            if (!Schema::hasColumn('products', 'is_active'))
                $t->boolean('is_active')->default(true)->index();

            if (!Schema::hasColumn('products', 'stock'))
                $t->integer('stock')->default(0);

            if (!Schema::hasColumn('products', 'supplier_id'))
                $t->unsignedBigInteger('supplier_id')->nullable()->index();

            if (!Schema::hasColumn('products', 'supplier_stock_code'))
                $t->string('supplier_stock_code', 191)->nullable()->index();

            if (!Schema::hasColumn('products', 'sku'))
                $t->string('sku', 191)->nullable()->unique();

            if (!Schema::hasColumn('products', 'category_path'))
                $t->text('category_path')->nullable();

            // description zaten varsa atlayın; yoksa ekleyin:
            if (!Schema::hasColumn('products', 'description'))
                $t->longText('description')->nullable();
        });
    }

    public function down(): void {
        // İsteğe bağlı: eklediğiniz kolonları geri alın
    }
};