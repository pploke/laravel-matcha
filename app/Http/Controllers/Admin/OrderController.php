<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = trim((string) $request->query('q'));

        $orders = Order::query()
            ->with(['items.product', 'items.variant'])
            ->when($status && array_key_exists($status, Order::STATUS_LABELS), fn ($query) => $query->where('order_status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('customer_name', 'like', "%{$q}%")
                        ->orWhere('customer_phone', 'like', "%{$q}%")
                        ->orWhere('id', $q);
                });
            })
            ->latest()
            ->get();

        $stats = [
            'total' => Order::count(),
            'processing' => Order::where('order_status', Order::STATUS_PROCESSING)->count(),
            'completed' => Order::where('order_status', Order::STATUS_COMPLETED)->count(),
            'cancelled' => Order::where('order_status', Order::STATUS_CANCELLED)->count(),
        ];

        $statusLabels = Order::STATUS_LABELS;

        return view('admin.orders.index', compact('orders', 'stats', 'status', 'q', 'statusLabels'));
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'items.variant', 'user', 'payment']);
        $statusLabels = Order::STATUS_LABELS;
        return view('admin.orders.show', compact('order', 'statusLabels'));
    }

    public function receipt(Order $order)
    {
        $order->load(['items.product', 'items.variant', 'payment']);
        return view('orders.receipt', [
            'order' => $order,
            'backUrl' => route('admin.orders.show', $order),
        ]);
    }

    public function updateStatus(Request $request, Order $order, StockService $stockService)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', [
                Order::STATUS_PROCESSING,
                Order::STATUS_COMPLETED,
                Order::STATUS_CANCELLED,
            ])],
        ], [
            'status.required' => 'Status pesanan wajib dipilih.',
            'status.in' => 'Status pesanan tidak valid.',
        ]);

        return DB::transaction(function () use ($data, $order, $stockService) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Pesanan harus lunas sebelum admin boleh menyelesaikannya.
            if ($data['status'] === Order::STATUS_COMPLETED && $order->payment_status !== 'PAID') {
                return back()->with('error', 'Pesanan '.$order->code.' belum lunas. Tandai lunas terlebih dahulu sebelum menyelesaikan pesanan.');
            }

            // Setelah stok dikembalikan, pesanan batal tidak boleh diaktifkan
            // lagi tanpa mekanisme potong stok ulang.
            if ($order->order_status === Order::STATUS_CANCELLED
                && $order->stock_restored_at
                && $data['status'] !== Order::STATUS_CANCELLED) {
                return back()->with('error', 'Pesanan '.$order->code.' sudah dibatalkan dan stoknya telah dikembalikan.');
            }

            $wasCancelled = $order->order_status === Order::STATUS_CANCELLED;
            $order->order_status = $data['status'];

            // Laporan harian memakai completed_at, jadi penanda waktu harus ikut
            // diperbarui saat status akhir berubah.
            if ($data['status'] === Order::STATUS_COMPLETED) {
                $order->completed_at = $order->completed_at ?? now();
                $order->cancelled_at = null;
            } elseif ($data['status'] === Order::STATUS_CANCELLED) {
                $order->cancelled_at = $order->cancelled_at ?? now();
                $order->completed_at = null;
            } else {
                $order->completed_at = null;
                $order->cancelled_at = null;
            }

            $order->save();

            if ($data['status'] === Order::STATUS_CANCELLED && ! $wasCancelled) {
                $stockService->restoreForOrder($order);
            }

            return back()->with('success', 'Status pesanan '.$order->code.' berhasil diperbarui menjadi '.$order->status_label.'.');
        });
    }

    /**
     * Menandai pesanan tunai sebagai lunas secara manual oleh admin.
     * Pesanan tunai tidak memiliki baris pembayaran Midtrans.
     */
    public function markPaid(Order $order)
    {
        if (! $order->isCashPayment()) {
            return back()->with('error', 'Hanya pesanan dengan metode tunai yang dapat ditandai lunas secara manual.');
        }

        if ($order->payment_status === 'PAID') {
            return back()->with('error', 'Pesanan '.$order->code.' sudah lunas.');
        }

        if ($order->order_status === Order::STATUS_CANCELLED) {
            return back()->with('error', 'Pesanan '.$order->code.' sudah dibatalkan dan tidak dapat ditandai lunas.');
        }

        $order->payment_status = 'PAID';
        $order->paid_at = $order->paid_at ?? now();
        $order->expires_at = null;

        // Pembayaran tunai diterima di gerai: pesanan langsung masuk proses.
        if ($order->order_status === Order::STATUS_PENDING) {
            $order->order_status = Order::STATUS_PROCESSING;
        }

        $order->save();

        return back()->with('success', 'Pembayaran tunai pesanan '.$order->code.' berhasil ditandai lunas.');
    }
}
