<?php

namespace Database\Factories;

use App\Models\Product; // ✅ EKLE
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class; // ✅ MODELE BAĞLA

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 50, 1500),
            'stock' => $this->faker->numberBetween(0, 500),
        ];
    }
}
