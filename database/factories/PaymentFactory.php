<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $gross = $this->faker->numberBetween(30000, 120000);

        return [
            'order_id' => Order::factory(),
            'provider' => 'midtrans',
            'midtrans_order_id' => 'MID-' . strtoupper(Str::random(8)),
            'transaction_id' => 'TRX-' . $this->faker->unique()->numerify('######'),
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'gopay',
            'gross_amount' => $gross,
            'snap_token' => 'dummy_snap_' . Str::random(10),
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/dummy',
            'raw_response' => json_encode(['status_message' => 'success', 'gross_amount' => $gross]),
            'paid_at' => now(),
            'expires_at' => now()->addHours(1),
        ];
    }
}