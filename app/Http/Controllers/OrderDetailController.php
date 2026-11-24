<?php

namespace App\Http\Controllers;

use App\Models\Order;

/**
 * Modul 4.4 (sisi konsumen): detail pesanan dan struk.
 * Hanya pemilik akun atau tamu yang pesanannya tersimpan di session yang boleh melihat.
 */
class OrderDetailController extends Controller
{
    public function show(Order $order)
    {
        $this->authorizeOrderAccess($order);

        $order->load(['items.product', 'items.variant', 'payment']);

        return view('orders.show', compact('order'));
    }

    public function receipt(Order $order)
    {
        $this->authorizeOrderAccess($order);

        $order->load(['items.product', 'items.variant', 'payment']);

        return view('orders.receipt', [
            'order' => $order,
            'backUrl' => route('orders.show', $order),
        ]);
    }

    private function authorizeOrderAccess(Order $order): void
    {
        $userId = auth()->id();
        $guestOrders = session('guest_orders', []);

        $isOwner = ($userId && (int) $order->user_id === (int) $userId)
            || in_array($order->id, $guestOrders);

        // 403: user lain tidak boleh melihat pesanan orang lain.
        abort_unless($isOwner, 403, 'Anda tidak berhak melihat pesanan ini.');
    }
}