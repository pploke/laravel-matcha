<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $deliveryType = $this->faker->randomElement(['antar', 'ambil']);
        $shippingFee = $deliveryType === 'antar' ? 10000 : 0;
        $subtotal = $this->faker->numberBetween(25000, 100000);

        return [
            'user_id' => User::factory(),
            'order_number' => 'MATCHA-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
            'customer_name' => $this->faker->name(),
            'customer_phone' => $this->faker->phoneNumber(),
            'shipping_address' => $deliveryType === 'antar' ? $this->faker->address() : 'Ambil di tempat',
            'shipping_fee' => $shippingFee,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'total_amount' => $subtotal + $shippingFee,
            'delivery_type' => $deliveryType,
            'payment_method' => Order::PAYMENT_METHOD_BANK_TRANSFER,
            'order_status' => 'PROCESSING',
            'payment_status' => 'UNPAID',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}