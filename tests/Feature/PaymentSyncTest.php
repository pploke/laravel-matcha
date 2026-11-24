<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeOnlineOrder(User $user): array
    {
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => 'PENDING',
            'payment_method' => Order::PAYMENT_METHOD_BANK_TRANSFER,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'gross_amount' => $order->total_amount,
            'payment_status' => 'PENDING',
            'transaction_status' => 'pending',
            'paid_at' => null,
        ]);

        return [$order, $payment];
    }

    private function markPaidInDatabase(Order $order, Payment $payment): void
    {
        $order->forceFill([
            'order_status' => Order::STATUS_PROCESSING,
            'payment_status' => 'PAID',
            'paid_at' => now(),
        ])->save();

        $payment->forceFill([
            'transaction_status' => 'settlement',
            'payment_status' => 'PAID',
            'paid_at' => now(),
        ])->save();
    }

    public function test_status_endpoint_syncs_from_midtrans(): void
    {
        $user = User::factory()->create();
        [$order] = $this->makeOnlineOrder($user);

        $this->partialMock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncFromMidtrans')->once();
        });

        $this->actingAs($user)
            ->get(route('payment.status', $order))
            ->assertOk()
            ->assertJsonPath('payment_status', 'PENDING')
            ->assertJsonPath('order_status', Order::STATUS_PENDING);
    }

    public function test_payment_page_syncs_first_and_does_not_create_a_new_token_when_paid(): void
    {
        $user = User::factory()->create();
        [$order, $payment] = $this->makeOnlineOrder($user);

        $this->partialMock(MidtransService::class, function (MockInterface $mock) use ($order, $payment) {
            $mock->shouldReceive('syncFromMidtrans')
                ->once()
                ->andReturnUsing(fn () => $this->markPaidInDatabase($order, $payment));
            $mock->shouldNotReceive('createSnapTransaction');
        });

        $this->actingAs($user)
            ->get(route('payment.show', $order))
            ->assertOk()
            ->assertSee('LUNAS');

        $this->assertSame('PAID', $order->fresh()->payment_status);
    }

    public function test_generate_token_syncs_first_and_skips_when_already_paid(): void
    {
        $user = User::factory()->create();
        [$order, $payment] = $this->makeOnlineOrder($user);

        $this->partialMock(MidtransService::class, function (MockInterface $mock) use ($order, $payment) {
            $mock->shouldReceive('syncFromMidtrans')
                ->once()
                ->andReturnUsing(fn () => $this->markPaidInDatabase($order, $payment));
            $mock->shouldNotReceive('createSnapTransaction');
        });

        $this->actingAs($user)
            ->postJson(route('payment.token', $order))
            ->assertStatus(409);

        $this->assertSame('PAID', $order->fresh()->payment_status);
    }

    public function test_cash_order_does_not_call_midtrans_sync(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'order_status' => Order::STATUS_PROCESSING,
            'payment_status' => 'UNPAID',
        ]);

        $this->partialMock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('syncFromMidtrans');
            $mock->shouldNotReceive('createSnapTransaction');
        });

        $this->actingAs($user)
            ->get(route('payment.show', $order))
            ->assertOk()
            ->assertSee('Pembayaran Tunai');
    }

    public function test_history_page_syncs_pending_online_orders(): void
    {
        $user = User::factory()->create();
        [$order] = $this->makeOnlineOrder($user);

        $this->partialMock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncFromMidtrans')->once();
        });

        $this->actingAs($user)
            ->get(route('orders.history'))
            ->assertOk()
            ->assertSee($order->code);
    }
}
