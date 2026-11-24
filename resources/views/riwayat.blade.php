@php
    // Tahapan pesanan. Diantar: Diproses → Diantar → Selesai. Ambil: Diterima → Selesai.
    $tahap = [
        'antar' => [
            ['label' => 'Sedang Diproses', 'icon' => 'bi-cup-hot'],
            ['label' => 'Sedang Diantar', 'icon' => 'bi-bicycle'],
            ['label' => 'Selesai', 'icon' => 'bi-check-lg'],
        ],
        'ambil' => [
            ['label' => 'Pesanan Diterima', 'icon' => 'bi-bag-check'],
            ['label' => 'Selesai', 'icon' => 'bi-check-lg'],
        ],
    ];
@endphp

<x-layouts.dashboard :nav="\App\Support\Navigation::konsumen()" role-label="Konsumen"
    title="Riwayat Pesanan" subtitle="Pantau status pesanan yang kamu buat">
    @if ($orders->isEmpty())
        <div class="card-soft mx-auto max-w-lg p-10 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-matcha-100 text-2xl text-matcha-700"><i class="bi bi-clock-history"></i></div>
            <h2 class="mt-3 text-base font-bold text-matcha-900">Belum ada pesanan</h2>
            <p class="mt-1 text-sm text-stone-500">
                Pesanan yang kamu buat akan muncul di sini{{ auth()->check() ? '.' : ' (tersimpan di perangkat ini, masuk untuk menyimpannya di akun).' }}
            </p>
            <a href="{{ route('katalog.index') }}" class="btn-matcha mt-4 no-underline">Pilih Menu</a>
        </div>
    @else
        <div class="mx-auto w-full max-w-4xl space-y-4">
            @guest
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-matcha-200 bg-matcha-50 px-4 py-3 text-xs text-stone-600">
                    <span><i class="bi bi-info-circle mr-1.5 text-matcha-700"></i>Kamu belum masuk, jadi riwayat ini hanya tersimpan di perangkat/browser ini.</span>
                    <span>
                        <a href="{{ route('login') }}" class="font-semibold text-matcha-700">Masuk</a> atau
                        <a href="{{ route('register') }}" class="font-semibold text-matcha-700">daftar</a> untuk menyimpannya di akun.
                    </span>
                </div>
            @endguest

            @if ($newOrderId)
                <div class="flex animate-pop-in items-center gap-3 rounded-2xl bg-matcha-700 px-4 py-3 text-sm text-white" role="status">
                    <i class="bi bi-check-circle-fill text-lg"></i>
                    <span>Pesanan <b>#MB-{{ str_pad((string) $newOrderId, 5, '0', STR_PAD_LEFT) }}</b> berhasil dibuat. Silakan tunggu konfirmasi dari gerai.</span>
                </div>
            @endif

            @foreach ($orders as $order)
                @php
                    $kind = $order->delivery_type === 'ambil' ? 'ambil' : 'antar';
                    $steps = $tahap[$kind];
                    $done = $order->order_status === 'COMPLETED';
                    $trackable = in_array($order->order_status, ['PROCESSING', 'COMPLETED'], true);
                    $badgeLabel = $order->order_status === 'PROCESSING' ? $steps[0]['label'] : null;
                @endphp
                <div class="card-soft p-5 {{ $order->id === $newOrderId ? 'ring-2 ring-matcha-500' : '' }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="text-sm font-bold text-matcha-900">Pesanan {{ $order->code }}</div>
                            <div class="text-xs text-stone-500">
                                {{ $order->created_at?->locale('id')->translatedFormat('d M Y, H:i') }}
                                · <i class="bi {{ $order->payment_method_icon }}"></i> {{ $order->payment_method_label }}
                            </div>
                        </div>
                        <x-status-badge :status="$order->order_status" :label="$badgeLabel" />
                    </div>

                    @if ($order->order_status === 'CANCELLED' && $order->payment_status === 'PAID')
                        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800" role="alert">
                            <i class="bi bi-exclamation-triangle mr-1"></i>
                            <b>Pembayaran diterima setelah pesanan dibatalkan/kedaluwarsa — perlu refund.</b>
                            Pesanan tetap dibatalkan dan stok tidak dipotong ulang. Hubungi gerai untuk proses refund.
                        </div>
                    @endif

                    @if ($trackable)
                        <ol class="mt-3 flex items-start" aria-label="Tahapan pesanan">
                            @foreach ($steps as $i => $step)
                                @php
                                    $isDone = $done || $i < 0;
                                    $isNow = ! $done && $i === 0;
                                @endphp
                                <li class="relative flex flex-1 flex-col items-center text-center">
                                    @if ($i > 0)
                                        <span class="absolute right-1/2 top-4 h-0.5 w-full {{ $done ? 'bg-matcha-700' : 'bg-matcha-100' }}"></span>
                                    @endif
                                    <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full text-sm {{ $isDone ? 'bg-matcha-700 text-white' : ($isNow ? 'bg-matcha-700 text-white ring-4 ring-matcha-200' : 'bg-matcha-100 text-matcha-700') }}">
                                        <i class="bi {{ $isDone ? 'bi-check-lg' : $step['icon'] }}"></i>
                                    </span>
                                    <span class="mt-1 text-[11px] {{ $isDone || $isNow ? 'font-bold text-matcha-900' : 'text-stone-400' }}">{{ $step['label'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    <ul class="mt-3 divide-y divide-matcha-100/70 text-sm">
                        @foreach ($order->items as $item)
                            <li class="flex justify-between gap-3 py-2">
                                <div>
                                    <div class="font-semibold text-matcha-900">{{ $item->product?->name ?? 'Produk dihapus' }}</div>
                                    <div class="text-xs text-stone-500">{{ $item->variant?->variant_name ?? 'Tanpa varian' }} · {{ $item->quantity }}x</div>
                                </div>
                                <div class="shrink-0 font-semibold text-stone-700">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-2 grid gap-4 border-t border-matcha-100 pt-3 text-sm sm:grid-cols-2">
                        <div class="text-xs text-stone-500">
                            <div class="font-semibold text-stone-600">{{ $kind === 'ambil' ? 'Ambil sendiri' : 'Diantar' }}</div>
                            <div class="mt-0.5">{{ $order->shipping_address }}</div>
                            <div class="mt-1">{{ $order->customer_name }} · {{ $order->customer_phone }}</div>
                        </div>
                        <dl class="space-y-1">
                            <div class="flex justify-between">
                                <dt class="text-stone-500">Subtotal</dt>
                                <dd>Rp {{ number_format((int) $order->total_amount - (int) $order->shipping_fee, 0, ',', '.') }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-stone-500">Ongkir</dt>
                                <dd>{{ (int) $order->shipping_fee > 0 ? 'Rp '.number_format((int) $order->shipping_fee, 0, ',', '.') : 'Gratis' }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-matcha-100 pt-1 font-bold text-matcha-900">
                                <dt>Total</dt>
                                <dd class="text-matcha-700">Rp {{ number_format((int) $order->total_amount, 0, ',', '.') }}</dd>
                            </div>
                            <div class="flex justify-between text-xs text-stone-500">
                                <dt>Pembayaran</dt>
                                <dd>Tunai</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Status Pembayaran & Tombol Aksi (Sub-bab 3.2.11) -->
                    @php
                        $paymentTone = match (true) {
                            $order->payment_status === 'PAID' => 'bg-emerald-100 text-emerald-800',
                            in_array($order->payment_status, ['EXPIRED', 'CANCELLED', 'DENIED', 'REFUNDED'], true) => 'bg-rose-100 text-rose-800',
                            default => 'bg-amber-100 text-amber-800',
                        };
                        $needsOnlinePayment = ! $order->isCashPayment()
                            && in_array($order->payment_status, ['UNPAID', 'PENDING', ''], true)
                            && ! in_array($order->order_status, ['CANCELLED', 'COMPLETED'], true);
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-4 mt-4 pt-3 border-t border-stone-100">
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-stone-500">Status Pembayaran:</span>
                            <span class="px-2.5 py-1 text-xs font-bold rounded-full {{ $paymentTone }}">
                                {{ $order->payment_status_label }}
                            </span>
                        </div>

                        <div>
                            @if($needsOnlinePayment)
                                <a href="{{ route('payment.show', $order) }}" class="btn-matcha !text-xs !py-1.5 !px-3 no-underline inline-flex items-center">
                                    <i class="bi bi-credit-card mr-1"></i> Bayar Sekarang
                                </a>
                            @else
                                <a href="{{ route('payment.show', $order) }}" class="text-xs font-semibold text-matcha-700 hover:underline inline-flex items-center">
                                    <i class="bi bi-eye mr-1"></i> Detail Pesanan
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>