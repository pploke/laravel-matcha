<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CartStockTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Product, ProductVariant} */
    private function productWithStock(int $stock): array
    {
        $product = Product::factory()->create([
            'base_price' => 25000,
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

    public function test_add_rejects_quantity_above_stock(): void
    {
        [$product, $variant] = $this->productWithStock(2);
        $cart = app(CartService::class);

        try {
            $cart->add($product->id, $variant->id, '50%', '', 3);
            $this->fail('Seharusnya stok tidak mencukupi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cart', $e->errors());
        }

        $this->assertSame([], $cart->items());
    }

    public function test_add_checks_total_quantity_across_rows_with_same_variant(): void
    {
        [$product, $variant] = $this->productWithStock(5);
        $cart = app(CartService::class);

        $cart->add($product->id, $variant->id, '50%', '', 3);

        try {
            $cart->add($product->id, $variant->id, '100%', '', 3);
            $this->fail('Seharusnya total qty varian melebihi stok.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cart', $e->errors());
        }

        $this->assertSame(3, $cart->count());
    }

    public function test_update_rejects_quantity_above_stock(): void
    {
        [$product, $variant] = $this->productWithStock(2);
        $cart = app(CartService::class);

        $cart->add($product->id, $variant->id, '50%', '', 1);
        $rowId = array_key_first($cart->items());

        try {
            $cart->update($rowId, 3);
            $this->fail('Seharusnya stok tidak mencukupi.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cart', $e->errors());
        }

        $this->assertSame(1, $cart->count());
    }

    public function test_variant_is_required_for_product_with_variants(): void
    {
        [$product] = $this->productWithStock(5);
        $cart = app(CartService::class);

        try {
            $cart->add($product->id, null, '50%', '', 1);
            $this->fail('Seharusnya varian wajib dipilih.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cart', $e->errors());
        }

        $this->assertSame([], $cart->items());
    }

    public function test_rows_expose_stock_and_integer_money(): void
    {
        [$product, $variant] = $this->productWithStock(5);
        $cart = app(CartService::class);

        $cart->add($product->id, $variant->id, '50%', '', 2);
        $row = $cart->rows()[0];

        $this->assertSame(5, $row['stock']);
        $this->assertSame(25000, $row['unit_price']);
        $this->assertSame(50000, $row['subtotal']);
        $this->assertSame(50000, $cart->subtotal());
    }
}
