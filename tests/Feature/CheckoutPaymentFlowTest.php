<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\Payment\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckoutPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_a_pending_order_and_redirects_to_payment(): void
    {
        $product = Product::factory()->create([
            'name' => 'Matcha Latte',
            'base_price' => 25000,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapTransaction')
                ->once()
                ->andReturn([
                    'token' => 'dummy-snap-token',
                    'redirect_url' => 'https://example.test/pay',
                ]);
        });

        $token = (string) Str::uuid();

        $cart = [
            'row-1' => [
                'product_id' => $product->id,
                'variant_id' => null,
                'name' => $product->name,
                'variant_name' => null,
                'tone' => 'latte',
                'image' => null,
                'sweet' => 'Normal',
                'note' => '',
                'unit_price' => 25000,
                'qty' => 2,
            ],
        ];

        $response = $this->withSession(['cart' => $cart, 'checkout_tokens' => [$token]])->post('/checkout', [
            'name' => 'Budi',
            'phone' => '08123456789',
            'type' => 'ambil',
            'pickup' => 'Secepatnya (± 15 menit)',
            'checkout_token' => $token,
            'payment_method' => Order::PAYMENT_METHOD_QRIS,
        ]);

        $order = Order::firstOrFail();

        $this->assertSame(Order::PAYMENT_METHOD_QRIS, $order->payment_method);
        $this->assertSame(Order::STATUS_PENDING, $order->order_status);
        $this->assertSame('UNPAID', $order->payment_status);
        $this->assertNotNull($order->expires_at);
        $this->assertSame(50000, (int) $order->total_amount);
        $this->assertSame(1, $order->items()->count());

        $response->assertRedirect(route('payment.show', $order));
    }

    public function test_checkout_still_saves_the_order_when_midtrans_fails(): void
    {
        $product = Product::factory()->create([
            'base_price' => 25000,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapTransaction')
                ->once()
                ->andThrow(new \Exception('Midtrans down'));
        });

        $token = (string) Str::uuid();

        $cart = [
            'row-1' => [
                'product_id' => $product->id,
                'variant_id' => null,
                'name' => $product->name,
                'variant_name' => null,
                'tone' => 'latte',
                'image' => null,
                'sweet' => 'Normal',
                'note' => '',
                'unit_price' => 25000,
                'qty' => 1,
            ],
        ];

        $response = $this->withSession(['cart' => $cart, 'checkout_tokens' => [$token]])->post('/checkout', [
            'name' => 'Budi',
            'phone' => '08123456789',
            'type' => 'ambil',
            'checkout_token' => $token,
            'payment_method' => Order::PAYMENT_METHOD_BANK_TRANSFER,
        ]);

        $order = Order::firstOrFail();

        $this->assertSame(Order::PAYMENT_METHOD_BANK_TRANSFER, $order->payment_method);
        $this->assertSame(Order::STATUS_PENDING, $order->order_status);
        $this->assertSame('UNPAID', $order->payment_status);
        $response->assertRedirect(route('payment.show', $order));
        $response->assertSessionHas('error');
    }

    public function test_cash_checkout_skips_midtrans_and_creates_a_processing_order(): void
    {
        $product = Product::factory()->create([
            'base_price' => 25000,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createSnapTransaction');
        });

        $token = (string) Str::uuid();

        $cart = [
            'row-1' => [
                'product_id' => $product->id,
                'variant_id' => null,
                'name' => $product->name,
                'variant_name' => null,
                'tone' => 'latte',
                'image' => null,
                'sweet' => 'Normal',
                'note' => '',
                'unit_price' => 25000,
                'qty' => 1,
            ],
        ];

        $response = $this->withSession(['cart' => $cart, 'checkout_tokens' => [$token]])->post('/checkout', [
            'name' => 'Budi',
            'phone' => '08123456789',
            // Ongkir antar sekarang dihitung dinamis via RajaOngkir dan diuji
            // di ShippingCheckoutTest; fokus test ini adalah alur pembayaran tunai.
            'type' => 'ambil',
            'pickup' => 'Secepatnya (± 15 menit)',
            'checkout_token' => $token,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
        ]);

        $order = Order::firstOrFail();

        $this->assertSame(Order::PAYMENT_METHOD_CASH, $order->payment_method);
        $this->assertSame(Order::STATUS_PROCESSING, $order->order_status);
        $this->assertSame('UNPAID', $order->payment_status);
        $this->assertNull($order->expires_at);
        $this->assertSame(0, $order->payment()->count());
        $this->assertSame(25000, (int) $order->total_amount);
        $response->assertRedirect(route('payment.show', $order));
        $response->assertSessionHas('success');
    }
}
