<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product; // 🔹 Bunu ekle

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::factory()->count(10)->create();
    }
}