<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'stock_code'       => strtoupper($this->faker->bothify('P####S####')),
            'name'             => $this->faker->words(3, true),
            'buy_price_vat'    => $this->faker->randomFloat(2, 100, 8000),
            'commission_rate'  => $this->faker->numberBetween(8, 25),
            'width'            => $this->faker->randomFloat(2, 1, 50),
            'length'           => $this->faker->randomFloat(2, 1, 50),
            'height'           => $this->faker->randomFloat(2, 1, 50),
        ];
    }
}
