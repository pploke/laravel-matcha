<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payment\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected MidtransService $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    /**
     * Log tidak boleh menggagalkan alur pembayaran.
     */
    private function logWarning(string $message, array $context = []): void
    {
        try {
            Log::warning($message, $context);
        } catch (\Throwable $e) {
            // Diabaikan dengan sengaja.
        }
    }

    /**
     * sinkronkan status dari Midtrans sebelum memutuskan membuat token baru.
     */
    private function syncOnlineOrder(Order $order): void
    {
        if (
            ! $order->usesMidtrans()
            || $order->payment_status === 'PAID'
            || ! $order->payment?->midtrans_order_id
        ) {
            return;
        }

        try {
            $this->midtransService->syncFromMidtrans($order);
            $order->refresh();
        } catch (\Throwable $e) {
            $this->logWarning('Sync status Midtrans gagal', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pastikan pengguna berhak melihat/membayar order ini.
     * Pemilik akun atau tamu yang order-nya tersimpan di session boleh akses.
     */
    private function authorizeOrderAccess(Order $order): void
    {
        $userId = auth()->id();
        $guestOrders = session('guest_orders', []);
        $isOwner = ($userId && (int) $order->user_id === (int) $userId)
            || in_array($order->id, $guestOrders);

        abort_unless($isOwner, 403, 'Unauthorized action.');
    }

    /**
     * Membuat atau mengambil token pembayaran Snap untuk sebuah order.
     */
    public function generateToken(Request $request, Order $order): JsonResponse|RedirectResponse
    {
        $this->authorizeOrderAccess($order);

        if ($order->isCashPayment()) {
            $message = 'Pesanan ini dibayar tunai. Admin akan menandainya lunas setelah pembayaran diterima.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 409)
                : back()->with('error', $message);
        }

        // Cek dulu ke Midtrans: webhook bisa saja gagal sehingga order masih
        // terlihat belum dibayar padahal transaksinya sudah settlement.
        $this->syncOnlineOrder($order);

        if ($order->payment_status === 'PAID') {
            return $request->expectsJson()
                ? response()->json(['message' => 'Order sudah lunas.'], 409)
                : back()->with('error', 'Pesanan ini sudah lunas.');
        }

        if ($order->order_status === Order::STATUS_CANCELLED) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Order sudah dibatalkan.'], 400)
                : back()->with('error', 'Pesanan ini sudah dibatalkan.');
        }

        try {
            $result = $this->midtransService->createSnapTransaction($order);
        } catch (\Throwable $e) {
            $this->logWarning('Gagal membuat token Midtrans', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return $request->expectsJson()
                ? response()->json([
                    'status' => 'error',
                    'message' => 'Gagal membuat transaksi Midtrans: ' . $e->getMessage(),
                ], 500)
                : back()->with('error', 'Gagal membuat transaksi Midtrans: ' . $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Token pembayaran berhasil dibuat.',
                'data' => $result,
            ]);
        }

        return back()->with('success', 'Token pembayaran berhasil dibuat.');
    }

    /**
     * Menampilkan halaman pembayaran untuk sebuah order.
     */
    public function show(Order $order)
    {
        $this->authorizeOrderAccess($order);

        $order->load(['items.product', 'payment']);

        // Jangan pernah membuat Snap baru sebelum memastikan status terbaru
        // dari Midtrans (webhook bisa gagal atau datang terlambat).
        $this->syncOnlineOrder($order);
        $order->load(['items.product', 'payment']);

        $paymentError = null;
        $tokenExpired = $order->payment?->expires_at?->isPast() ?? false;
        $needsToken = ! $order->payment?->snap_token || $tokenExpired;

        // Pesanan tunai tidak memakai Snap; halaman hanya menampilkan
        // instruksi pembayaran di gerai.
        if (
            $order->usesMidtrans()
            && $needsToken
            && $order->payment_status !== 'PAID'
            && $order->order_status !== Order::STATUS_CANCELLED
        ) {
            try {
                $this->midtransService->createSnapTransaction($order);
                $order->refresh()->load(['items.product', 'payment']);
            } catch (\Throwable $e) {
                $paymentError = $e->getMessage();
                $this->logWarning('Gagal menyiapkan pembayaran Midtrans', [
                    'order_id' => $order->id,
                    'error' => $paymentError,
                ]);
            }
        }

        return view('payments.show', compact('order', 'paymentError'));
    }

    /**
     * Mengembalikan status pembayaran dalam format JSON untuk keperluan polling.
     */
    public function status(Order $order)
    {
        $this->authorizeOrderAccess($order);

        // Jika belum lunas, tanya langsung ke Midtrans (tidak bergantung webhook/ngrok).
        $this->syncOnlineOrder($order);

        $payment = $order->payment()->first();

        return response()->json([
            'status' => $payment->transaction_status ?? 'pending',
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'expires_at' => $payment?->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Menerima webhook notifikasi dari server Midtrans.
     */
    public function notification(Request $request, MidtransService $midtransService)
    {
        try {
            $midtransService->handleNotification($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Notification processed successfully.',
            ]);
        } catch (\InvalidArgumentException $e) {
            // Payload invalid / signature tidak bisa diverifikasi / order tidak dikenal.
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid notification payload.',
            ], 400);
        } catch (\Throwable $e) {
            // Error internal (termasuk Status API gagal): beri 500 agar Midtrans retry.
            $this->logWarning('Gagal memproses webhook Midtrans', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Notification could not be processed.',
            ], 500);
        }
    }
}
