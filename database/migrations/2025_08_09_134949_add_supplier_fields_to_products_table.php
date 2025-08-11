<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) products tablosuna EK kolonlar (koşullu)
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'brand')) {
                $table->string('brand')->nullable();
            }
            if (! Schema::hasColumn('products', 'category_path')) {
                $table->string('category_path')->nullable();
            }
            if (! Schema::hasColumn('products', 'stock_amount')) {
                $table->integer('stock_amount')->default(0);
            }
            if (! Schema::hasColumn('products', 'currency_code')) {
                $table->string('currency_code', 8)->nullable();
            }
            if (! Schema::hasColumn('products', 'vat_rate')) {
                $table->decimal('vat_rate', 5, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'gtin')) {
                $table->string('gtin', 32)->nullable();
            }
            if (! Schema::hasColumn('products', 'volumetric_weight')) {
                $table->decimal('volumetric_weight', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'images')) {
                $table->json('images')->nullable();
            }
            if (! Schema::hasColumn('products', 'description')) {
                $table->longText('description')->nullable();
            }
        });

        // 2) entegrasyon tabloları — sadece yoksa oluştur
        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('code')->unique(); // örn: yenitoptanci
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_feeds')) {
            Schema::create('supplier_feeds', function (Blueprint $t) {
                $t->id();
                $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $t->string('url');
                $t->enum('format', ['xml','csv','json'])->default('xml');
                $t->json('auth')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('imports')) {
            Schema::create('imports', function (Blueprint $t) {
                $t->id();
                $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $t->foreignId('supplier_feed_id')->nullable()->constrained('supplier_feeds')->nullOnDelete();
                $t->string('status')->default('pending'); // running|done|failed
                $t->timestamp('started_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->text('error')->nullable();
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_products')) {
            Schema::create('supplier_products', function (Blueprint $t) {
                $t->id();
                $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $t->string('stock_code')->index();
                $t->string('name')->nullable();
                $t->decimal('buy_price_vat', 12, 2)->nullable();
                $t->decimal('vat_rate', 5, 2)->nullable();
                $t->decimal('commission_rate', 5, 2)->nullable();
                $t->string('currency', 3)->default('TRY');
                $t->integer('stock_amount')->nullable();
                $t->string('category_path')->nullable();
                $t->json('images')->nullable();
                $t->longText('description')->nullable();
                $t->json('dims')->nullable();          // width,height,depth,weight
                $t->json('raw')->nullable();           // orijinal node json
                $t->timestamp('last_seen_at')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unique(['supplier_id','stock_code']);
            });
        }
    }

    public function down(): void
    {
        // entegrasyon tablolarını kaldır
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('imports');
        Schema::dropIfExists('supplier_feeds');
        Schema::dropIfExists('suppliers');

        // products’tan eklediğimiz alanları geri al (varsa)
        Schema::table('products', function (Blueprint $table) {
            foreach ([
                'brand','category_path','stock_amount','currency_code','vat_rate',
                'gtin','volumetric_weight','images','description'
            ] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
