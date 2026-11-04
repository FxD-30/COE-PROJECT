<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->words(3, true)),
            'sku' => Str::upper(fake()->unique()->bothify('???-####')),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 1, 5000),
            'stock' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
