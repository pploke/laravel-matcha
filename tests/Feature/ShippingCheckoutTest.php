<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShippingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const RATE_URL = 'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rajaongkir.base_url' => 'https://rajaongkir.komerce.id/api/v1',
            'services.rajaongkir.api_key' => 'test-api-key',
            'services.rajaongkir.origin_id' => '70284',
            'services.rajaongkir.origin_label' => 'KAMAL, KAMAL, BANGKALAN, JAWA TIMUR, 69162',
            'services.rajaongkir.cache_ttl' => 600,
        ]);

        Cache::flush();
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Matcha Powder',
            'base_price' => 45000,
            'weight_grams' => 500,
            'is_active' => true,
        ], $overrides));
    }

    private function seedCart(Product $product, int $qty): void
    {
        app(CartService::class)->add($product->id, null, 'normal', '', $qty);
    }

    private function newToken(): string
    {
        $token = (string) Str::uuid();
        $this->withSession(['checkout_tokens' => [$token]]);

        return $token;
    }

    /** @return array<string, mixed> */
    private function payload(string $token, string $type = 'antar', array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi',
            'phone' => '08123456789',
            'type' => $type,
            'address' => $type === 'antar' ? 'Jl. Mawar No. 1' : null,
            'pickup' => $type === 'ambil' ? 'Secepatnya (± 15 menit)' : null,
            'checkout_token' => $token,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'shipping_destination_id' => '46720',
            'shipping_destination_label' => 'ARJOSARI, BLIMBING, MALANG, JAWA TIMUR, 65126',
            'shipping_postal_code' => '65126',
            'shipping_courier' => 'jne',
            'shipping_service' => 'REG',
        ], $overrides);
    }

    private function fakeSuccessfulRate(int $cost = 16000): void
    {
        Http::fake([
            self::RATE_URL => Http::response([
                'data' => [[
                    'code' => 'jne',
                    'name' => 'JNE',
                    'service' => 'REG',
                    'description' => 'Reguler',
                    'cost' => $cost,
                    'etd' => '2-3',
                ]],
            ], 200),
        ]);
    }

    public function test_pickup_rates_endpoint_returns_free_without_external_api(): void
    {
        Http::fake();

        $response = $this->getJson('/shipping/rates?type=ambil');

        $response->assertOk()
            ->assertJson([
                'type' => 'ambil',
                'weight_grams' => 0,
                'shipping_fee' => 0,
                'label' => 'Gratis',
            ]);

        Http::assertNothingSent();
    }

    public function test_pickup_checkout_sets_zero_fee_and_skips_rajaongkir(): void
    {
        Http::fake();

        $product = $this->createProduct(['base_price' => 45000, 'weight_grams' => 500]);
        $token = $this->newToken();
        $this->seedCart($product, 2);

        $this->post('/checkout', $this->payload($token, 'ambil', [
            'shipping_fee' => 50000,
            'shipping_weight_grams' => 2000,
        ]))->assertRedirect();

        Http::assertNothingSent();

        $order = Order::firstOrFail();
        $this->assertSame(0, (int) $order->shipping_fee);
        $this->assertSame(0, (int) $order->shipping_weight_grams);
        $this->assertNull($order->shipping_courier);
        $this->assertNull($order->shipping_service);
        $this->assertNull($order->shipping_destination_id);
        $this->assertNull($order->shipping_etd);
        $this->assertSame(90000, (int) $order->total_amount);
    }

    public function test_delivery_checkout_rejects_invalid_service_with_error_bag(): void
    {
        $this->fakeSuccessfulRate();

        $product = $this->createProduct(['name' => 'Matcha Latte Mix', 'base_price' => 30000]);
        $token = $this->newToken();
        $this->seedCart($product, 1);

        $this->from('/checkout')->post('/checkout', $this->payload($token, 'antar', [
            'shipping_service' => 'LAYANAN_TIDAK_VALID',
        ]))
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors(['shipping_service']);

        $this->assertSame(0, Order::count());
    }

    public function test_delivery_checkout_calculates_weight_and_fee_on_server_and_ignores_client_values(): void
    {
        $this->fakeSuccessfulRate(16000);

        $product = $this->createProduct(['name' => 'Ceremonial Matcha Tin', 'base_price' => 60000]);
        $token = $this->newToken();
        // 3 * 500 = 1500 gram dihitung di server; subtotal = 3 * 60000 = 180000
        $this->seedCart($product, 3);

        $this->post('/checkout', $this->payload($token, 'antar', [
            'shipping_fee' => 100,
            'shipping_weight_grams' => 10,
        ]))->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/calculate/domestic-cost')
                && (int) ($request['weight'] ?? 0) === 1500
                && (string) ($request['destination'] ?? '') === '46720';
        });

        $order = Order::firstOrFail();
        $this->assertSame(16000, (int) $order->shipping_fee);
        $this->assertSame(1500, (int) $order->shipping_weight_grams);
        $this->assertSame('jne', $order->shipping_courier);
        $this->assertSame('REG', $order->shipping_service);
        $this->assertSame('46720', $order->shipping_destination_id);
        $this->assertSame('65126', $order->shipping_postal_code);
        $this->assertSame('2-3', $order->shipping_etd);
        $this->assertSame(180000, (int) $order->subtotal);
        $this->assertSame(180000 + 16000, (int) $order->total_amount);
    }

    public function test_delivery_checkout_returns_form_error_when_rajaongkir_fails(): void
    {
        Http::fake([
            self::RATE_URL => Http::response(['message' => 'server error'], 500),
        ]);

        $product = $this->createProduct();
        $token = $this->newToken();
        $this->seedCart($product, 1);

        $this->from('/checkout')->post('/checkout', $this->payload($token))
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors(['shipping_service']);

        $this->assertSame(0, Order::count());
    }

    public function test_delivery_checkout_requires_destination_courier_and_service(): void
    {
        Http::fake();

        $product = $this->createProduct();
        $token = $this->newToken();
        $this->seedCart($product, 1);

        $this->from('/checkout')->post('/checkout', $this->payload($token, 'antar', [
            'shipping_destination_id' => null,
            'shipping_courier' => null,
            'shipping_service' => null,
        ]))
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors([
                'shipping_destination_id',
                'shipping_courier',
                'shipping_service',
            ]);

        Http::assertNothingSent();
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_rejects_empty_cart_before_calling_rajaongkir(): void
    {
        $this->fakeSuccessfulRate();
        $token = $this->newToken();

        $this->from('/checkout')->post('/checkout', $this->payload($token))
            ->assertRedirect('/checkout')
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(0, Order::count());
    }

    public function test_delivery_checkout_keeps_idempotency_for_double_submit(): void
    {
        $this->fakeSuccessfulRate();

        $product = $this->createProduct();
        $token = $this->newToken();
        $this->seedCart($product, 1);
        $payload = $this->payload($token);

        $this->post('/checkout', $payload)->assertRedirect();
        $order = Order::firstOrFail();

        $this->post('/checkout', $payload)
            ->assertRedirect(route('payment.show', $order));

        $this->assertSame(1, Order::count());
    }

    public function test_checkout_page_renders_with_zero_initial_shipping_and_token(): void
    {
        $product = $this->createProduct();
        $this->seedCart($product, 1);

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('name="checkout_token"', false)
            ->assertSee('name="shipping_destination_id"', false)
            ->assertSee('name="shipping_service"', false)
            ->assertSee('Rp 0');
    }
}
