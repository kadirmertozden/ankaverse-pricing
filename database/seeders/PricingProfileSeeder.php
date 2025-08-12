<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PricingProfileSeeder extends Seeder
{
    public function run(): void
    {
        // Zaten varsa dokunma
        $exists = DB::table('pricing_profiles')->where('id', 1)->exists();
        if ($exists) {
            return;
        }

        // Supplier 1 varsa bağla, yoksa null
        $supplierId = DB::table('suppliers')->where('id', 1)->exists() ? 1 : null;

        DB::table('pricing_profiles')->insert([
            'id' => 1,
            'name' => 'Yenitoptanci varsayılan',
            'supplier_id' => $supplierId,
            'min_margin' => 25,
            'commission_percent' => 10,
            'vat_percent' => 20,
            'currency' => 'TRY',
            'rounding' => 0.99,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
