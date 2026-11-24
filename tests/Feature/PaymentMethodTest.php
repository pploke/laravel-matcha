<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Payment\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private function cashOrder(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'order_status' => Order::STATUS_PROCESSING,
            'payment_status' => 'UNPAID',
            'expires_at' => null,
        ], $overrides));
    }

    public function test_cash_payment_page_shows_instructions_without_requesting_a_snap_token(): void
    {
        $user = User::factory()->create();
        $order = $this->cashOrder(['user_id' => $user->id, 'total_amount' => 35000]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createSnapTransaction');
        });

        $this->actingAs($user)
            ->get(route('payment.show', $order))
            ->assertOk()
            ->assertSee('Pembayaran Tunai')
            ->assertSee('35.000')
            ->assertDontSee('Buka Pembayaran Midtrans');
    }

    public function test_cash_order_cannot_request_a_midtrans_token(): void
    {
        $user = User::factory()->create();
        $order = $this->cashOrder(['user_id' => $user->id]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createSnapTransaction');
        });

        $this->actingAs($user)
            ->post(route('payment.token', $order))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_admin_can_mark_a_cash_order_as_paid(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $order = $this->cashOrder();

        $this->actingAs($admin)
            ->patch(route('admin.orders.paid', $order))
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('PAID', $order->payment_status);
        $this->assertSame(Order::STATUS_PROCESSING, $order->order_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(0, $order->payment()->count());
    }

    public function test_admin_cannot_manually_mark_an_online_order_as_paid(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $order = Order::factory()->create([
            'payment_method' => Order::PAYMENT_METHOD_QRIS,
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => 'UNPAID',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.orders.paid', $order))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('UNPAID', $order->refresh()->payment_status);
    }

    public function test_admin_cannot_complete_an_unpaid_order(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $order = $this->cashOrder();

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_COMPLETED])
            ->assertRedirect()
            ->assertSessionHas('error');

        $order->refresh();

        $this->assertSame(Order::STATUS_PROCESSING, $order->order_status);
        $this->assertNull($order->completed_at);
    }

    public function test_admin_completing_a_paid_order_sets_completed_at(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $order = $this->cashOrder([
            'payment_status' => 'PAID',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_COMPLETED])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(Order::STATUS_COMPLETED, $order->order_status);
        $this->assertNotNull($order->completed_at);
        $this->assertNull($order->cancelled_at);
    }

    public function test_admin_cancelling_an_order_sets_cancelled_at(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $order = $this->cashOrder();

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => Order::STATUS_CANCELLED])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame(Order::STATUS_CANCELLED, $order->order_status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertNull($order->completed_at);
    }

    public function test_expire_command_skips_cash_orders(): void
    {
        $cash = $this->cashOrder([
            'order_status' => Order::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $online = Order::factory()->create([
            'payment_method' => Order::PAYMENT_METHOD_QRIS,
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => 'PENDING',
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('orders:expire-unpaid')->assertExitCode(0);

        $this->assertSame(Order::STATUS_PENDING, $cash->refresh()->order_status);
        $this->assertSame(Order::STATUS_CANCELLED, $online->refresh()->order_status);
        $this->assertNotNull($online->cancelled_at);
    }
}
