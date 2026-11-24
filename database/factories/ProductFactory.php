<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'base_price' => 25000,
            'cost_price' => 12000,
            'image_url' => 'https://example.com/matcha.jpg',
            'is_active' => true,
        ];
    }
}