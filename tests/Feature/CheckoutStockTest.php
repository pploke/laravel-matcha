<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutStockTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Product, ProductVariant} */
    private function productWithStock(int $stock, int $price = 25000): array
    {
        $product = Product::factory()->create([
            'name' => 'Matcha Latte',
            'base_price' => $price,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'variant_name' => 'Size M',
            'additional_price' => 0,
            'stock' => $stock,
        ]);

        return [$product, $variant];
    }

    /** @return array<string, mixed> */
    private function cartRow(Product $product, ProductVariant $variant, int $qty): array
    {
        $rowId = md5($product->id.'|'.$variant->id.'|50%|');

        return [
            $rowId => [
                'row_id' => $rowId,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'name' => $product->name,
                'variant_name' => $variant->variant_name,
                'tone' => 'latte',
                'image' => null,
                'sweet' => '50%',
                'note' => '',
                'unit_price' => (int) $product->base_price,
                'qty' => $qty,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function payload(string $token): array
    {
        return [
            'name' => 'Budi',
            'phone' => '08123456789',
            'type' => 'ambil',
            'checkout_token' => $token,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
        ];
    }

    public function test_checkout_decrements_variant_stock_and_marks_deduction(): void
    {
        [$product, $variant] = $this->productWithStock(5);
        $token = (string) Str::uuid();

        $this->withSession([
            'cart' => $this->cartRow($product, $variant, 2),
            'checkout_tokens' => [$token],
        ])->post('/checkout', $this->payload($token))->assertRedirect();

        $this->assertSame(3, $variant->fresh()->stock);

        $order = Order::firstOrFail();
        $this->assertNotNull($order->stock_deducted_at);
        $this->assertNull($order->stock_restored_at);
        $this->assertSame(50000, $order->subtotal);
        $this->assertSame(50000, $order->total_amount);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_checkout_rejects_quantity_above_stock_and_rolls_back(): void
    {
        [$product, $variant] = $this->productWithStock(2);
        $token = (string) Str::uuid();

        $this->withSession([
            'cart' => $this->cartRow($product, $variant, 3),
            'checkout_tokens' => [$token],
        ])->post('/checkout', $this->payload($token))
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(2, $variant->fresh()->stock);
    }

    public function test_checkout_requires_variant_for_product_with_variants(): void
    {
        [$product, $variant] = $this->productWithStock(5);
        $token = (string) Str::uuid();

        $cart = $this->cartRow($product, $variant, 1);
        $rowId = array_key_first($cart);
        $cart[$rowId]['variant_id'] = null;

        $this->withSession([
            'cart' => $cart,
            'checkout_tokens' => [$token],
        ])->post('/checkout', $this->payload($token))
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::count());
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_double_submit_with_same_token_creates_only_one_order(): void
    {
        [$product, $variant] = $this->productWithStock(10);
        $token = (string) Str::uuid();

        $session = [
            'cart' => $this->cartRow($product, $variant, 1),
            'checkout_tokens' => [$token],
        ];

        $this->withSession($session)->post('/checkout', $this->payload($token))
            ->assertRedirect();

        $order = Order::firstOrFail();

        $this->withSession($session)->post('/checkout', $this->payload($token))
            ->assertRedirect(route('payment.show', $order));

        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderItem::count());
        $this->assertSame(9, $variant->fresh()->stock);
    }

    public function test_checkout_rejects_missing_token(): void
    {
        [$product, $variant] = $this->productWithStock(5);

        $payload = $this->payload((string) Str::uuid());
        unset($payload['checkout_token']);

        $this->withSession([
            'cart' => $this->cartRow($product, $variant, 1),
            'checkout_tokens' => [],
        ])->post('/checkout', $payload)
            ->assertSessionHasErrors('checkout_token');

        $this->assertSame(0, Order::count());
    }

    public function test_money_values_are_integers(): void
    {
        [$product, $variant] = $this->productWithStock(5, 25500);
        $token = (string) Str::uuid();

        $this->withSession([
            'cart' => $this->cartRow($product, $variant, 2),
            'checkout_tokens' => [$token],
        ])->post('/checkout', $this->payload($token))->assertRedirect();

        $order = Order::firstOrFail();
        $item = $order->items()->firstOrFail();

        $this->assertSame(25500, $item->unit_price);
        $this->assertSame(51000, $item->subtotal);
        $this->assertSame(51000, $order->subtotal);
        $this->assertSame(51000, $order->total_amount);
    }
}
