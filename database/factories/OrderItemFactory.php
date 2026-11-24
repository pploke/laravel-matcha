<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'quantity' => $this->faker->numberBetween(1, 3),
            'sweetness' => $this->faker->randomElement(['Normal', '50%', 'Less Sugar', 'No Sugar']),
            'note' => $this->faker->optional()->word(),
            'unit_price' => 25000,
        ];
    }
}