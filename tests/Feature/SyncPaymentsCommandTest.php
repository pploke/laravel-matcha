<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncPaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingOnlineOrder(): array
    {
        $order = Order::factory()->create([
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

    public function test_sync_command_accepts_an_order_id(): void
    {
        [$order] = $this->makePendingOnlineOrder();

        $this->partialMock(MidtransService::class, function (MockInterface $mock) use ($order) {
            $mock->shouldReceive('syncFromMidtrans')->once()->with(\Mockery::on(
                fn (Order $actual) => $actual->id === $order->id
            ));
        });

        $this->artisan('payments:sync', ['order' => $order->id])
            ->expectsOutputToContain('Selesai: 1 disinkronkan')
            ->assertExitCode(0);
    }

    public function test_sync_command_accepts_an_order_number(): void
    {
        [$order] = $this->makePendingOnlineOrder();

        $this->partialMock(MidtransService::class, function (MockInterface $mock) use ($order) {
            $mock->shouldReceive('syncFromMidtrans')->once()->with(\Mockery::on(
                fn (Order $actual) => $actual->id === $order->id
            ));
        });

        $this->artisan('payments:sync', ['order' => $order->order_number])
            ->assertExitCode(0);
    }

    public function test_sync_command_with_hours_option_skips_old_orders(): void
    {
        [$recent] = $this->makePendingOnlineOrder();
        [$old] = $this->makePendingOnlineOrder();
        $old->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->partialMock(MidtransService::class, function (MockInterface $mock) use ($recent) {
            $mock->shouldReceive('syncFromMidtrans')->once()->with(\Mockery::on(
                fn (Order $actual) => $actual->id === $recent->id
            ));
        });

        $this->artisan('payments:sync', ['--hours' => 24])
            ->expectsOutputToContain('Selesai: 1 disinkronkan, 0 dilewati, 0 gagal.')
            ->assertExitCode(0);
    }

    public function test_sync_command_fails_for_unknown_order(): void
    {
        $this->artisan('payments:sync', ['order' => 999999])
            ->expectsOutputToContain('tidak ditemukan')
            ->assertExitCode(1);
    }

    public function test_sync_command_reports_a_failed_status_api_call(): void
    {
        [$order] = $this->makePendingOnlineOrder();

        $this->partialMock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncFromMidtrans')
                ->once()
                ->andThrow(new \RuntimeException('Midtrans timeout'));
        });

        $this->artisan('payments:sync', ['order' => $order->id])
            ->expectsOutputToContain('Selesai: 0 disinkronkan, 0 dilewati, 1 gagal.')
            ->assertExitCode(1);
    }
}
