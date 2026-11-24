@php
    $paymentStatus = $order->payment_status ?? 'UNPAID';
    $isPaid = $paymentStatus === 'PAID';
    $isCancelled = $order->order_status === 'CANCELLED';
    $isExpired = in_array($paymentStatus, ['EXPIRED', 'CANCELLED', 'CANCEL', 'REFUNDED', 'DENIED', 'deny'], true);
    $isFinal = $isPaid || $isExpired || $isCancelled;

    $badgeClasses = match (true) {
        $isPaid => 'bg-emerald-100 text-emerald-800',
        $isExpired || $isCancelled => 'bg-rose-100 text-rose-800',
        default => 'bg-amber-100 text-amber-800',
    };

    $badgeLabel = match ($paymentStatus) {
        'PAID' => 'LUNAS',
        'PENDING' => 'MENUNGGU PEMBAYARAN',
        'UNPAID' => 'BELUM DIBAYAR',
        'EXPIRED' => 'KEDALUWARSA',
        'CANCELLED' => 'DIBATALKAN',
        'CANCEL' => 'DIBATALKAN',
        'DENIED' => 'DITOLAK',
        'deny' => 'DITOLAK',
        'REFUNDED' => 'DIKEMBALIKAN',
        default => strtoupper((string) $paymentStatus),
    };

    $snapToken = $order->isCashPayment() ? null : $order->payment?->snap_token;
    $tokenExpired = $order->payment?->expires_at?->isPast() ?? false;
    $canPay = ! $isFinal;
    $hasUsableToken = $snapToken && ! $tokenExpired;
    $isCash = $order->isCashPayment();

    $snapUrl = config('services.midtrans.snap_base_url', 'https://app.sandbox.midtrans.com/snap/snap.js');
@endphp

<x-layouts.dashboard :nav="\App\Support\Navigation::konsumen()" role-label="Konsumen"
    title="Pembayaran Pesanan" subtitle="Selesaikan pembayaran pesanan Anda" :show-cart="false">

    <div class="card-soft mx-auto max-w-2xl p-6 sm:p-8">
        <!-- Header Status & Nomor Pesanan -->
        <div class="flex justify-between items-center gap-3 border-b border-matcha-100 pb-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-matcha-900">Pesanan {{ $order->order_number ?? $order->code }}</h2>
                <p class="text-xs text-stone-500">Dibuat pada {{ $order->created_at->format('d M Y, H:i') }}</p>
                <p class="mt-1 text-xs font-semibold text-matcha-700">
                    <i class="bi {{ $order->payment_method_icon }} mr-1"></i>{{ $order->payment_method_label }}
                </p>
            </div>
            <span id="payment-status-badge" class="px-3 py-1 text-xs font-bold rounded-full text-center {{ $badgeClasses }}">
                {{ $badgeLabel }}
            </span>
        </div>

        @if($order->order_status === 'CANCELLED' && $order->payment_status === 'PAID')
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
                <div class="font-bold"><i class="bi bi-exclamation-triangle mr-1"></i> Pembayaran diterima setelah pesanan dibatalkan — perlu refund</div>
                <p class="mt-1 text-xs">
                    Dana sudah tercatat lunas di Midtrans, tetapi pesanan tetap dibatalkan dan stok tidak dipotong ulang.
                    Silakan hubungi gerai untuk proses refund.
                </p>
            </div>
        @endif

        <!-- Hitung Mundur Kedaluwarsa -->
        @if(! $isFinal && $order->payment && $order->payment->expires_at)
            <div class="mb-6 p-3 bg-amber-50 border border-amber-200 rounded-xl flex justify-between items-center text-xs text-amber-800">
                <span><i class="bi bi-clock mr-1"></i> Batas Waktu Pembayaran:</span>
                <span id="countdown" class="font-bold text-sm">Memuat...</span>
            </div>
        @endif

        <!-- Ringkasan Pesanan -->
        <div class="space-y-3 mb-6">
            @foreach($order->items as $item)
                <div class="flex justify-between gap-3 text-sm">
                    <span class="text-stone-700">
                        {{ $item->quantity }}× {{ $item->product->name ?? 'Produk' }}
                        @if($item->variant)
                            <span class="text-xs text-stone-500">({{ $item->variant->variant_name }})</span>
                        @endif
                    </span>
                    <span class="shrink-0 font-semibold text-stone-900">Rp {{ number_format($item->unit_price * $item->quantity, 0, ',', '.') }}</span>
                </div>
            @endforeach

            @if($order->shipping_fee > 0)
                <div class="flex justify-between text-sm border-t border-stone-100 pt-2">
                    <span class="text-stone-700">Ongkir</span>
                    <span class="font-semibold text-stone-900">Rp {{ number_format($order->shipping_fee, 0, ',', '.') }}</span>
                </div>
            @endif

            <div class="flex justify-between text-base font-extrabold border-t border-matcha-100 pt-3 text-matcha-900">
                <span>Total Tagihan</span>
                <span>Rp {{ number_format($order->total_amount ?? $order->subtotal, 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- Tombol Aksi Pembayaran / Status -->
        <div class="mt-6 text-center" id="action-container">
            @if($isPaid)
                <div class="p-4 bg-emerald-50 text-emerald-800 rounded-xl font-medium text-sm">
                    🎉 Pembayaran telah berhasil diselesaikan! Pesanan Anda sedang diproses.
                </div>
                <a href="{{ route('orders.history') }}" class="btn-matcha mt-4 inline-block no-underline">Lihat Riwayat Pesanan</a>
            @elseif($isCancelled || $isExpired)
                <div class="p-4 bg-rose-50 text-rose-800 rounded-xl font-medium text-sm">
                    Pesanan ini sudah tidak dapat dibayar ({{ $badgeLabel }}). Silakan buat pesanan baru.
                </div>
                <a href="{{ route('katalog.index') }}" class="btn-matcha mt-4 inline-block no-underline">Pilih Menu Lagi</a>
            @elseif($isCash)
                <div class="rounded-xl border border-matcha-200 bg-matcha-50 p-4 text-left">
                    <div class="flex items-center gap-2 text-sm font-bold text-matcha-900">
                        <i class="bi bi-cash-coin text-lg text-matcha-700"></i> Pembayaran Tunai
                    </div>
                    <p class="mt-2 text-sm text-stone-600">
                        @if($order->delivery_type === 'ambil')
                            Silakan bayar tunai di gerai saat mengambil pesanan.
                        @else
                            Silakan siapkan uang tunai dan bayar saat pesanan diantar (COD).
                        @endif
                    </p>
                    <div class="mt-3 flex items-center justify-between rounded-lg bg-white px-3 py-2 text-sm">
                        <span class="text-stone-600">Total yang harus dibayar</span>
                        <span class="font-bold text-matcha-700">Rp {{ number_format($order->total_amount ?? $order->subtotal, 0, ',', '.') }}</span>
                    </div>
                    <p class="mt-3 text-xs text-stone-500">
                        Status pembayaran akan diperbarui oleh admin setelah uang tunai diterima. Tidak ada pembayaran online yang perlu diselesaikan.
                    </p>
                </div>
                <a href="{{ route('orders.history') }}" class="btn-matcha mt-4 inline-block no-underline">Lihat Riwayat Pesanan</a>
            @else
                @if($hasUsableToken)
                    <button id="pay-button" class="btn-matcha w-full py-3 text-base shadow-md">
                        <i class="bi bi-credit-card mr-2"></i> Buka Pembayaran Midtrans
                    </button>
                    <p class="mt-3 text-xs text-stone-500">
                        Pembayaran diproses oleh Midtrans. Setelah membayar, status akan diperbarui otomatis.
                    </p>
                @else
                    @if($paymentError)
                        <div class="p-4 bg-rose-50 text-rose-800 rounded-xl text-sm mb-4 text-left">
                            <div class="font-semibold">Gagal menyiapkan pembayaran</div>
                            <div class="mt-1 break-words">{{ $paymentError }}</div>
                        </div>
                    @else
                        <div class="p-4 bg-amber-50 text-amber-800 rounded-xl text-sm mb-4">
                            Token pembayaran belum tersedia. Klik tombol di bawah untuk membuatnya.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('payment.token', $order) }}">
                        @csrf
                        <button type="submit" class="btn-matcha w-full py-3 text-base shadow-md">
                            <i class="bi bi-arrow-clockwise mr-2"></i> Buat Pembayaran
                        </button>
                    </form>
                @endif

                <a href="{{ route('orders.history') }}" class="mt-4 block text-sm font-semibold text-matcha-700 hover:underline">Cek Status di Riwayat Pesanan</a>
            @endif
        </div>
    </div>

    @if(! $isCash && $hasUsableToken && $canPay)
        <script src="{{ $snapUrl }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    @endif

    <script type="text/javascript">
        (function () {
            const orderId = {{ $order->id }};
            const initialStatus = @json($paymentStatus);
            const isFinal = @json($isFinal);
            const isCash = @json($isCash);
            const snapToken = @json($hasUsableToken ? $snapToken : null);

            const refreshStatus = function (reloadOnChange) {
                return fetch(`/payments/${orderId}/status`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        return response.ok ? response.json() : null;
                    })
                    .then(function (data) {
                        if (reloadOnChange !== false && data && data.payment_status && data.payment_status !== initialStatus) {
                            location.reload();
                        }
                        return data;
                    })
                    .catch(function () {
                        return null;
                    });
            };

            // 1. Tombol Snap
            const payButton = document.getElementById('pay-button');

            if (payButton && snapToken) {
                payButton.addEventListener('click', function () {
                    if (typeof window.snap === 'undefined') {
                        alert('Skrip Midtrans belum termuat. Periksa koneksi internet lalu muat ulang halaman.');
                        return;
                    }

                    window.snap.pay(snapToken, {
                        onSuccess: function () {
                            refreshStatus(false).finally(function () { location.reload(); });
                        },
                        onPending: function () {
                            refreshStatus(true);
                        },
                        onClose: function () {
                            refreshStatus(true);
                        },
                        onError: function () {
                            refreshStatus(false).finally(function () { location.reload(); });
                        }
                    });
                });
            }

            // 2. Countdown timer
            const countdownEl = document.getElementById('countdown');
            @if(! $isFinal && $order->payment && $order->payment->expires_at)
                const expiresAt = new Date(@json($order->payment->expires_at->toIso8601String())).getTime();

                const timerInterval = setInterval(function () {
                    const distance = expiresAt - Date.now();

                    if (distance < 0) {
                        clearInterval(timerInterval);
                        if (countdownEl) {
                            countdownEl.textContent = 'Waktu habis';
                        }
                        refreshStatus(true);
                        return;
                    }

                    const minutes = Math.floor(distance / 60000);
                    const seconds = Math.floor((distance % 60000) / 1000);

                    if (countdownEl) {
                        countdownEl.textContent = `${minutes}m ${seconds < 10 ? '0' : ''}${seconds}s`;
                    }
                }, 1000);
            @endif

            // 3. Polling status (sinkron ke Midtrans, tanpa perlu webhook).
            //    Pesanan tunai juga dipantau agar perubahan dari admin terlihat.
            if (! isFinal) {
                const pollInterval = setInterval(function () {
                    refreshStatus(true);
                }, isCash ? 5000 : 3000);

                window.addEventListener('beforeunload', function () {
                    clearInterval(pollInterval);
                });
            }
        })();
    </script>
</x-layouts.dashboard>
