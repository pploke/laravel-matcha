<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/payments/midtrans/notification';

    private function makePendingPayment(): array
    {
        $order = Order::factory()->create([
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => 'PENDING',
            'payment_method' => Order::PAYMENT_METHOD_BANK_TRANSFER,
            'subtotal' => 51000,
            'shipping_fee' => 0,
            'total_amount' => 51000,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'gross_amount' => 51000,
            'payment_status' => 'PENDING',
            'transaction_status' => 'pending',
            'paid_at' => null,
        ]);

        return [$order, $payment];
    }

    private function payload(
        string $orderId,
        string $transactionStatus = 'settlement',
        string $statusCode = '200',
        array $overrides = []
    ): array {
        $gross = '51000.00';

        return array_merge([
            'va_numbers' => [['va_number' => '3294643456040635449', 'bank' => 'bca']],
            'transaction_time' => now()->toDateTimeString(),
            'transaction_status' => $transactionStatus,
            'transaction_id' => 'trx-' . uniqid(),
            'status_message' => 'midtrans payment notification',
            'status_code' => $statusCode,
            'signature_key' => hash(
                'sha512',
                $orderId . $statusCode . $gross . config('services.midtrans.server_key')
            ),
            'payment_type' => 'bank_transfer',
            'gross_amount' => $gross,
            'fraud_status' => 'accept',
            'order_id' => $orderId,
            'currency' => 'IDR',
        ], $overrides);
    }

    public function test_valid_settlement_notification_marks_order_paid(): void
    {
        [$order, $payment] = $this->makePendingPayment();

        $this->postJson(self::ENDPOINT, $this->payload($payment->midtrans_order_id))
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $order->refresh();
        $payment->refresh();

        $this->assertSame('PAID', $order->payment_status);
        $this->assertSame(Order::STATUS_PROCESSING, $order->order_status);
        $this->assertSame('PAID', $payment->payment_status);
        $this->assertSame('settlement', $payment->transaction_status);
        $this->assertSame('bank_transfer', $payment->payment_type);
        $this->assertSame('accept', $payment->fraud_status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_pending_notification_keeps_order_pending(): void
    {
        [, $payment] = $this->makePendingPayment();

        $this->postJson(self::ENDPOINT, $this->payload(
            $payment->midtrans_order_id,
            'pending',
            '201'
        ))->assertOk();

        $this->assertSame('PENDING', $payment->fresh()->payment_status);
    }

    public function test_duplicate_settlement_is_idempotent(): void
    {
        [$order, $payment] = $this->makePendingPayment();
        $payload = $this->payload($payment->midtrans_order_id);

        $this->postJson(self::ENDPOINT, $payload)->assertOk();
        $paidAt = $payment->fresh()->paid_at;

        $this->postJson(self::ENDPOINT, $payload)->assertOk();

        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
        $this->assertSame(
            $paidAt->toDateTimeString(),
            $payment->fresh()->paid_at->toDateTimeString()
        );
    }

    public function test_incomplete_payload_returns_400(): void
    {
        [, $payment] = $this->makePendingPayment();
        $payload = $this->payload($payment->midtrans_order_id);
        unset($payload['signature_key']);

        $this->postJson(self::ENDPOINT, $payload)
            ->assertStatus(400)
            ->assertJson(['status' => 'error']);
    }

    public function test_unknown_order_returns_400(): void
    {
        $payload = $this->payload('TRX-999999-20260101000000');

        $this->postJson(self::ENDPOINT, $payload)->assertStatus(400);
    }

    public function test_amount_mismatch_returns_400(): void
    {
        [, $payment] = $this->makePendingPayment();
        $orderId = $payment->midtrans_order_id;
        $gross = '49000.00';
        $statusCode = '200';

        $this->postJson(self::ENDPOINT, [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $gross,
            'transaction_status' => 'settlement',
            'signature_key' => hash(
                'sha512',
                $orderId . $statusCode . $gross . config('services.midtrans.server_key')
            ),
        ])->assertStatus(400);

        $this->assertSame('PENDING', $payment->fresh()->payment_status);
    }

    public function test_invalid_signature_is_verified_through_status_api(): void
    {
        [, $payment] = $this->makePendingPayment();
        $orderId = $payment->midtrans_order_id;

        $this->partialMock(MidtransService::class, function (MockInterface $mock) use ($orderId) {
            $mock->shouldReceive('fetchStatus')
                ->once()
                ->with($orderId)
                ->andReturn([
                    'transaction_status' => 'settlement',
                    'fraud_status' => 'accept',
                    'gross_amount' => '51000.00',
                    'payment_type' => 'bank_transfer',
                    'transaction_id' => 'verified-by-status-api',
                ]);
        });

        $payload = $this->payload($orderId, 'pending', '201');
        $payload['signature_key'] = 'signature-tidak-cocok';

        $this->postJson(self::ENDPOINT, $payload)->assertOk();

        $this->assertSame('PAID', $payment->fresh()->payment_status);
        $this->assertSame('verified-by-status-api', $payment->fresh()->transaction_id);
    }

    public function test_status_api_failure_returns_500_so_midtrans_can_retry(): void
    {
        [, $payment] = $this->makePendingPayment();
        $orderId = $payment->midtrans_order_id;

        $this->partialMock(MidtransService::class, function (MockInterface $mock) use ($orderId) {
            $mock->shouldReceive('fetchStatus')
                ->once()
                ->with($orderId)
                ->andThrow(new \RuntimeException('Midtrans down'));
        });

        $payload = $this->payload($orderId, 'pending', '201');
        $payload['signature_key'] = 'salah';

        $this->postJson(self::ENDPOINT, $payload)->assertStatus(500);
        $this->assertSame('PENDING', $payment->fresh()->payment_status);
    }

    public function test_webhook_falls_back_to_order_prefix_when_token_was_regenerated(): void
    {
        [$order, $payment] = $this->makePendingPayment();
        $oldOrderId = 'TRX-' . $order->id . '-20260101000000';

        $payment->update(['midtrans_order_id' => 'TRX-' . $order->id . '-20260102000000']);

        $this->postJson(self::ENDPOINT, $this->payload($oldOrderId))
            ->assertOk();

        $this->assertSame('PAID', $order->fresh()->payment_status);
    }

    public function test_logging_failure_does_not_block_webhook(): void
    {
        [, $payment] = $this->makePendingPayment();
        $originalLogger = Log::getFacadeRoot();

        $brokenLogger = \Mockery::mock(LoggerInterface::class);
        $brokenLogger->shouldReceive('log')->andThrow(new \RuntimeException('log permission denied'));

        Log::swap($brokenLogger);

        try {
            $this->postJson(self::ENDPOINT, $this->payload($payment->midtrans_order_id))
                ->assertOk();
        } finally {
            Log::swap($originalLogger);
        }

        $this->assertSame('PAID', $payment->fresh()->payment_status);
    }
}
