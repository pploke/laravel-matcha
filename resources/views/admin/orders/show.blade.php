<x-layouts.dashboard :nav="\App\Support\Navigation::konsumen()" role-label="Konsumen"
    title="Detail Pesanan" subtitle="Rincian lengkap pesanan {{ $order->code }}">
    <div class="mx-auto w-full max-w-5xl">
        <a href="{{ route('orders.history') }}" class="mb-4 inline-flex items-center gap-1 text-xs font-semibold text-matcha-700 no-underline hover:underline">
            <i class="bi bi-arrow-left"></i> Kembali ke riwayat
        </a>

        @include('orders._detail', ['order' => $order, 'receiptUrl' => route('orders.receipt', $order)])

        @if (! $order->isCashPayment() && in_array($order->payment_status, ['UNPAID', 'PENDING'], true) && ! in_array($order->order_status, ['CANCELLED', 'COMPLETED'], true))
            <div class="mt-4">
                <a href="{{ route('payment.show', $order) }}" class="btn-matcha no-underline">
                    <i class="bi bi-credit-card"></i> Bayar Sekarang
                </a>
            </div>
        @endif
    </div>
</x-layouts.dashboard>