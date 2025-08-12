<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // NOT: Prod veriniz zaten doluysa, bu seed'i ÇALIŞTIRMAMANIZ önerilir.
        // Çalıştıracaksanız, unique alan stratejinize göre 'stock_code' veya 'sku'yu benzersiz varsaydık.

        $now = now();

        $rows = [
            [
                'stock_code'       => 'SEED-P001',
                'sku'              => 'SEED-P001',           // sku ZORUNLU: stock_code ile eşitledik
                'name'             => 'Seed Ürün 1',
                'buy_price_vat'    => 199.90,
                'commission_rate'  => 10,
                'width'            => 10.0,
                'length'           => 20.0,
                'height'           => 5.0,
                'brand'            => 'SeedBrand',
                'category_path'    => 'Seed/Kategori',
                'stock_amount'     => 10,
                'currency_code'    => 'TRY',
                'vat_rate'         => 20,
                'gtin'             => null,
                'volumetric_weight'=> 0,
                'images'           => null,
                'description'      => 'Seed ürünü açıklama',
                'sell_price'       => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'stock_code'       => 'SEED-P002',
                'sku'              => 'SEED-P002',
                'name'             => 'Seed Ürün 2',
                'buy_price_vat'    => 299.90,
                'commission_rate'  => 12,
                'width'            => 12.0,
                'length'           => 22.0,
                'height'           => 6.0,
                'brand'            => 'SeedBrand',
                'category_path'    => 'Seed/Kategori',
                'stock_amount'     => 15,
                'currency_code'    => 'TRY',
                'vat_rate'         => 20,
                'gtin'             => null,
                'volumetric_weight'=> 0,
                'images'           => null,
                'description'      => 'Seed ürünü açıklama 2',
                'sell_price'       => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
        ];

        foreach ($rows as $row) {
            // stock_code'u benzersiz kabul edip upsert yapıyoruz
            DB::table('products')->updateOrInsert(
                ['stock_code' => $row['stock_code']],
                $row
            );
        }
    }
}
