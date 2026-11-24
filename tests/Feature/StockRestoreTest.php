<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Payment\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockRestoreTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Product, ProductVariant, Order} */
    private function makeDeductedOrder(array $overrides = []): array
    {
        $product = Product::factory()->create(['base_price' => 25000]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'variant_name' => 'Size M',
            'additional_price' => 0,
            'stock' => 5,
        ]);

        $order = Order::factory()->create(array_merge([
            'order_status' => Order::STATUS_PROCESSING,
            'payment_status' => 'PAID',
            'stock_deducted_at' => now(),
        ], $overrides));

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 25000,
        ]);

        $variant->decrement('stock', 2);

        return [$product, $variant, $order];
    }

    public function test_admin_cancel_restores_stock_once(): void
    {
        [, $variant, $order] = $this->makeDeductedOrder();
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_CANCELLED])
            ->assertRedirect();

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertNotNull($order->fresh()->stock_restored_at);

        // Cancel ulang tidak boleh menambah stok dua kali.
        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_CANCELLED])
            ->assertRedirect();

        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_cancelled_order_cannot_be_reactivated_after_stock_is_restored(): void
    {
        [, $variant, $order] = $this->makeDeductedOrder();
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_CANCELLED])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_PROCESSING])
            ->assertSessionHas('error');

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->order_status);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_legacy_order_without_deduction_does_not_increase_stock(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'variant_name' => 'Size M',
            'additional_price' => 0,
            'stock' => 5,
        ]);

        $order = Order::factory()->create([
            'order_status' => Order::STATUS_PROCESSING,
            'payment_status' => 'PAID',
            'stock_deducted_at' => null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_CANCELLED])
            ->assertRedirect();

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    public function test_expire_command_restores_stock(): void
    {
        [, $variant, $order] = $this->makeDeductedOrder([
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => 'UNPAID',
            'payment_method' => Order::PAYMENT_METHOD_QRIS,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('orders:expire-unpaid')->assertExitCode(0);

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->order_status);
        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertNotNull($order->fresh()->stock_restored_at);
    }

    public function test_midtrans_expire_restores_stock_and_late_settlement_is_recorded_for_refund(): void
    {
        [, $variant, $order] = $this->makeDeductedOrder([
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => 'PENDING',
            'payment_method' => Order::PAYMENT_METHOD_QRIS,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'gross_amount' => $order->total_amount,
            'payment_status' => 'PENDING',
            'transaction_status' => 'pending',
            'paid_at' => null,
        ]);

        app(MidtransService::class)->applyStatus($payment, [
            'transaction_status' => 'expire',
            'fraud_status' => null,
            'gross_amount' => $order->total_amount,
        ]);

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertNotNull($order->fresh()->stock_restored_at);

        app(MidtransService::class)->applyStatus($payment->fresh(), [
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'gross_amount' => $order->total_amount,
        ]);

        // Kebijakan A: pembayaran tercatat lunas, order tetap batal, stok tidak berubah.
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->order_status);
        $this->assertSame('PAID', $order->fresh()->payment_status);
        $this->assertSame('PAID', $payment->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertStringContainsString('perlu refund', (string) $order->fresh()->notes);
        $this->assertSame(5, $variant->fresh()->stock);
    }
}
