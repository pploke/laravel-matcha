<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderWithPayment(array $orderOverrides = [], array $paymentOverrides = []): array
    {
        $order = Order::factory()->create(array_merge([
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => 'PENDING',
            'subtotal' => 41000,
            'shipping_fee' => 10000,
            'total_amount' => 51000,
        ], $orderOverrides));

        $payment = Payment::factory()->create(array_merge([
            'order_id' => $order->id,
            'gross_amount' => 51000,
            'payment_status' => 'PENDING',
            'transaction_status' => 'pending',
            'paid_at' => null,
        ], $paymentOverrides));

        return [$order, $payment];
    }

    public function test_settlement_marks_order_and_payment_as_paid(): void
    {
        [$order, $payment] = $this->makeOrderWithPayment();

        app(MidtransService::class)->applyStatus($payment, [
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'gross_amount' => '51000.00',
        ]);

        $order->refresh();
        $payment->refresh();

        $this->assertSame('PAID', $order->payment_status);
        $this->assertSame(Order::STATUS_PROCESSING, $order->order_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('PAID', $payment->payment_status);
        $this->assertSame('settlement', $payment->transaction_status);
    }

    public function test_pending_notification_does_not_downgrade_a_paid_order(): void
    {
        [$order, $payment] = $this->makeOrderWithPayment([
            'order_status' => Order::STATUS_PROCESSING,
            'payment_status' => 'PAID',
            'paid_at' => now(),
        ], [
            'payment_status' => 'PAID',
            'transaction_status' => 'settlement',
        ]);

        app(MidtransService::class)->applyStatus($payment, [
            'transaction_status' => 'pending',
            'fraud_status' => null,
            'gross_amount' => '51000.00',
        ]);

        $order->refresh();
        $payment->refresh();

        $this->assertSame('PAID', $order->payment_status);
        $this->assertSame('PAID', $payment->payment_status);
    }

    public function test_expire_cancels_the_order(): void
    {
        [$order, $payment] = $this->makeOrderWithPayment();

        app(MidtransService::class)->applyStatus($payment, [
            'transaction_status' => 'expire',
            'fraud_status' => null,
            'gross_amount' => '51000.00',
        ]);

        $order->refresh();
        $payment->refresh();

        $this->assertSame(Order::STATUS_CANCELLED, $order->order_status);
        $this->assertSame('EXPIRED', $order->payment_status);
        $this->assertSame('EXPIRED', $payment->payment_status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_late_settlement_does_not_downgrade_a_completed_order(): void
    {
        [$order, $payment] = $this->makeOrderWithPayment([
            'order_status' => Order::STATUS_COMPLETED,
            'payment_status' => 'PAID',
            'paid_at' => now(),
            'completed_at' => now(),
        ], [
            'payment_status' => 'PAID',
            'transaction_status' => 'settlement',
        ]);

        app(MidtransService::class)->applyStatus($payment, [
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'gross_amount' => '51000.00',
        ]);

        $order->refresh();

        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertSame('PAID', $order->payment_status);
    }

    public function test_payment_page_requests_a_token_when_none_exists(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => 'UNPAID',
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapTransaction')->once();
        });

        $this->actingAs($user)
            ->get(route('payment.show', $order))
            ->assertOk();
    }
}
