<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payment\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderHistoryController extends Controller
{
    public function index(Request $request, MidtransService $midtransService)
    {
        $with = ['items.product', 'items.variant'];

        if ($user = $request->user()) {
            // Konsumen melihat pesanan miliknya sendiri (aturan GET /orders lama).
            $orders = Order::with($with)
                ->where('user_id', $user->id)
                ->latest()
                ->get();
        } else {
            // Tamu: hanya pesanan yang dibuat dari perangkat/session ini.
            $guestOrderIds = session('guest_orders', []);

            $orders = empty($guestOrderIds)
                ? collect()
                : Order::with($with)->whereIn('id', $guestOrderIds)->latest()->get();
        }

        // Sinkronkan beberapa order online terbaru yang belum lunas. Gagal sync
        // tidak boleh menghalangi tampilan riwayat (mis. koneksi Midtrans down).
        $orders->filter(function (Order $order) {
            return $order->usesMidtrans()
                && $order->payment_status !== 'PAID'
                && $order->payment?->midtrans_order_id;
        })->take(5)->each(function (Order $order) use ($midtransService, $with) {
            try {
                $midtransService->syncFromMidtrans($order);
                $order->refresh()->load($with);
            } catch (\Throwable $e) {
                try {
                    Log::warning('Sync riwayat pesanan gagal', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable $ignored) {
                    // Abaikan kegagalan logging.
                }
            }
        });

        $newOrderId = session('new_order_id');

        return view('riwayat', compact('orders', 'newOrderId'));
    }
}
