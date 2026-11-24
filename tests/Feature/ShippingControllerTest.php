<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShippingControllerTest extends TestCase
{
    use RefreshDatabase;

    private const DESTINATION_URL = '*destination/domestic-destination*';

    private const RATE_URL = 'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rajaongkir.base_url' => 'https://rajaongkir.komerce.id/api/v1',
            'services.rajaongkir.api_key' => 'test-api-key',
            'services.rajaongkir.origin_id' => '70284',
            'services.rajaongkir.cache_ttl' => 600,
        ]);

        Cache::flush();
    }

    private function seedCart(int $qty = 2, int $weight = 500): void
    {
        $product = Product::create([
            'name' => 'Matcha Latte',
            'base_price' => 25000,
            'weight_grams' => $weight,
            'is_active' => true,
        ]);

        app(CartService::class)->add($product->id, null, 'normal', '', $qty);
    }

    public function test_destinations_rejects_query_shorter_than_three_characters(): void
    {
        Http::fake();

        $this->getJson('/shipping/destinations?q=ab')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);

        Http::assertNothingSent();
    }

    public function test_destinations_returns_normalized_results(): void
    {
        Http::fake([
            self::DESTINATION_URL => Http::response([
                'data' => [[
                    'id' => 46720,
                    'subdistrict_name' => 'Arjosari',
                    'city_name' => 'Malang',
                    'province_name' => 'Jawa Timur',
                    'zip_code' => '65126',
                ]],
            ], 200),
        ]);

        $this->getJson('/shipping/destinations?q=arjosari')
            ->assertOk()
            ->assertJson([
                'data' => [[
                    'id' => '46720',
                    'label' => 'Arjosari, Malang, Jawa Timur',
                    'zip_code' => '65126',
                ]],
            ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/destination/domestic-destination')
            && $request['search'] === 'arjosari');
    }

    public function test_destinations_returns_502_when_api_fails(): void
    {
        Http::fake([
            self::DESTINATION_URL => Http::response(['message' => 'error'], 500),
        ]);

        $this->getJson('/shipping/destinations?q=arjosari')->assertStatus(502);
    }

    public function test_rates_for_pickup_skips_external_api(): void
    {
        Http::fake();

        $this->getJson('/shipping/rates?type=ambil')
            ->assertOk()
            ->assertJson(['type' => 'ambil', 'shipping_fee' => 0]);

        Http::assertNothingSent();
    }

    public function test_rates_requires_destination_when_type_is_not_pickup(): void
    {
        Http::fake();

        $this->getJson('/shipping/rates')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destination_id']);

        Http::assertNothingSent();
    }

    public function test_rates_uses_cart_weight_and_allowed_couriers(): void
    {
        $this->seedCart(2, 750);

        Http::fake([
            self::RATE_URL => Http::response([
                'data' => [[
                    'code' => 'jne',
                    'name' => 'JNE',
                    'service' => 'REG',
                    'description' => 'Reguler',
                    'cost' => 18000,
                    'etd' => '2-3',
                ]],
            ], 200),
        ]);

        $this->getJson('/shipping/rates?type=antar&destination_id=46720&couriers=jne,unknown,pos')
            ->assertOk()
            ->assertJson([
                'type' => 'antar',
                'weight_grams' => 1500,
                'rates' => [[
                    'courier' => 'jne',
                    'service' => 'REG',
                    'cost' => 18000,
                    'etd' => '2-3',
                ]],
            ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/calculate/domestic-cost')
            && (int) $request['weight'] === 1500
            && (string) $request['destination'] === '46720'
            && (string) $request['courier'] === 'jne:pos');
    }

    public function test_rates_rejects_empty_cart(): void
    {
        Http::fake();

        $this->getJson('/shipping/rates?type=antar&destination_id=46720')
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_rates_returns_502_when_api_fails(): void
    {
        $this->seedCart();

        Http::fake([
            self::RATE_URL => Http::response(['message' => 'error'], 500),
        ]);

        $this->getJson('/shipping/rates?type=antar&destination_id=46720')
            ->assertStatus(502);
    }
}
