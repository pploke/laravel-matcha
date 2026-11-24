<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Payment\MidtransService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPayments extends Command
{
    protected $signature = 'payments:sync
                            {order? : ID atau nomor pesanan yang ingin disinkronkan}
                            {--hours=24 : Batas umur order saat tanpa argumen}';

    protected $description = 'Sinkronkan status pembayaran Midtrans ke tabel orders dan payments';

    public function handle(MidtransService $midtransService): int
    {
        $orderArg = $this->argument('order');

        if ($orderArg) {
            $order = Order::query()
                ->where('id', $orderArg)
                ->orWhere('order_number', $orderArg)
                ->first();

            if (! $order) {
                $this->error("Pesanan '{$orderArg}' tidak ditemukan.");

                return self::FAILURE;
            }

            $orders = collect([$order]);
        } else {
            $hours = max(1, (int) $this->option('hours'));

            $orders = Order::query()
                ->whereHas('payment', function ($query) {
                    $query->whereNotNull('midtrans_order_id');
                })
                ->where('payment_status', '!=', 'PAID')
                ->where('created_at', '>=', now()->subHours($hours))
                ->get();
        }

        $synced = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($orders as $order) {
            if ($order->payment_status === 'PAID' || ! $order->payment?->midtrans_order_id) {
                $skipped++;

                continue;
            }

            try {
                $midtransService->syncFromMidtrans($order);
                $synced++;
                $this->line(sprintf(
                    '<info>OK</info> %s (%s)',
                    $order->order_number ?: '#MB-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
                    $order->payment->midtrans_order_id
                ));
            } catch (\Throwable $e) {
                $failed++;

                try {
                    Log::warning('payments:sync gagal', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable $ignored) {
                    // Abaikan kegagalan logging.
                }

                $this->warn(sprintf(
                    'GAGAL %s: %s',
                    $order->order_number ?: '#' . $order->id,
                    $e->getMessage()
                ));
            }
        }

        $this->info("Selesai: {$synced} disinkronkan, {$skipped} dilewati, {$failed} gagal.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
