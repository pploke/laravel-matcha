<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Membatalkan pesanan yang belum dibayar hingga melewati batas waktu kedaluwarsa';

    public function handle(StockService $stockService): int
    {
        $expiredOrders = Order::query()
            ->where('order_status', Order::STATUS_PENDING)
            // Pesanan tunai tidak memiliki batas waktu pembayaran online.
            ->where(function ($query) {
                $query->whereNull('payment_method')
                    ->orWhere('payment_method', '!=', Order::PAYMENT_METHOD_CASH);
            })
            ->where(function ($query) {
                // Batas waktu utama ada di orders.expires_at.
                $query->where(function ($sub) {
                    $sub->whereNotNull('expires_at')
                        ->where('expires_at', '<', now());
                })
                // Fallback: data lama yang hanya punya expires_at di payments.
                ->orWhereHas('payment', function ($sub) {
                    $sub->where('payment_status', 'PENDING')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<', now());
                });
            })
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info('Tidak ada pesanan kedaluwarsa.');

            return self::SUCCESS;
        }

        foreach ($expiredOrders as $expiredOrder) {
            $cancelled = DB::transaction(function () use ($expiredOrder, $stockService) {
                $order = Order::whereKey($expiredOrder->id)
                    ->lockForUpdate()
                    ->first();

                // Bisa saja sudah dibayar/dibatalkan sebelum lock didapat.
                if (! $order || $order->order_status !== Order::STATUS_PENDING || $order->isCashPayment()) {
                    return false;
                }

                $order->order_status = Order::STATUS_CANCELLED;
                $order->payment_status = 'EXPIRED';
                $order->cancelled_at = $order->cancelled_at ?? now();
                $order->save();

                if ($order->payment) {
                    $order->payment->payment_status = 'EXPIRED';
                    $order->payment->transaction_status = 'expire';
                    $order->payment->save();
                }

                // Stok dipotong saat checkout, jadi kembalikan di sini.
                // StockService mengabaikan order lama yang belum pernah memotong stok.
                $stockService->restoreForOrder($order);

                return true;
            });

            if ($cancelled) {
                $this->info("Pesanan #{$expiredOrder->id} dibatalkan karena kedaluwarsa.");
            }
        }

        return self::SUCCESS;
    }
}
