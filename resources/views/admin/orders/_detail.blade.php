{{--
    Isi detail pesanan, dipakai bersama oleh halaman admin dan konsumen (modul 4.4).
    Variabel: $order (sudah di-load items/product/variant/payment), $receiptUrl.
--}}
@php
    $money = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $isDelivery = $order->delivery_type === 'antar';
    $subtotal = (int) ($order->subtotal ?: ((int) $order->total_amount - (int) $order->shipping_fee + (int) $order->discount_amount));
    $paymentTone = match (true) {
        $order->payment_status === 'PAID' => 'bg-emerald-100 text-emerald-800',
        in_array($order->payment_status, ['EXPIRED', 'CANCELLED', 'DENIED', 'REFUNDED'], true) => 'bg-rose-100 text-rose-800',
        default => 'bg-amber-100 text-amber-800',
    };
    $isCancelled = $order->order_status === 'CANCELLED';
    $isCompleted = $order->order_status === 'COMPLETED';
    $fmt = fn ($dt) => $dt?->locale('id')->translatedFormat('d M Y, H:i');

    // Timeline status pesanan
    $timeline = [
        ['label' => 'Pesanan dibuat', 'time' => $order->created_at, 'icon' => 'bi-receipt', 'done' => true],
        ['label' => 'Pembayaran diterima', 'time' => $order->paid_at, 'icon' => 'bi-cash-coin', 'done' => $order->payment_status === 'PAID'],
        $isCancelled
            ? ['label' => 'Pesanan dibatalkan', 'time' => $order->cancelled_at, 'icon' => 'bi-x-circle', 'done' => true, 'bad' => true]
            : ['label' => 'Pesanan selesai', 'time' => $order->completed_at, 'icon' => 'bi-check2-circle', 'done' => $isCompleted],
    ];
@endphp

<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        {{-- Kepala pesanan --}}
        <div class="card-soft p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-lg font-bold text-matcha-900">Pesanan {{ $order->code }}</div>
                    @if ($order->order_number)
                        <div class="text-xs text-stone-500">No. referensi: {{ $order->order_number }}</div>
                    @endif
                    <div class="text-xs text-stone-500">Dibuat {{ $fmt($order->created_at) }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-status-badge :status="$order->order_status" />
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $paymentTone }}">{{ $order->payment_status_label }}</span>
                </div>
            </div>

            @if ($isCancelled && $order->payment_status === 'PAID')
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800" role="alert">
                    <i class="bi bi-exclamation-triangle mr-1"></i>
                    <b>Perlu refund:</b> pembayaran diterima setelah pesanan dibatalkan/kedaluwarsa.
                </div>
            @endif

            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ $receiptUrl }}" target="_blank" rel="noopener" class="btn-matcha !py-1.5 text-xs no-underline">
                    <i class="bi bi-printer"></i> Cetak Struk
                </a>
            </div>
        </div>

        {{-- Item pesanan --}}
        <div class="card-soft p-5">
            <h2 class="mb-3 text-sm font-bold text-matcha-900">Item Pesanan</h2>
            <ul class="divide-y divide-matcha-100/70 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3 py-3">
                        <div>
                            <div class="font-semibold text-matcha-900">{{ $item->product?->name ?? 'Produk dihapus' }}</div>
                            <div class="text-xs text-stone-500">
                                {{ $item->variant?->variant_name ?? 'Tanpa varian' }}
                                @if ($item->sweetness) · Gula: {{ $item->sweetness }} @endif
                                · {{ $item->quantity }} × {{ $money($item->unit_price) }}
                            </div>
                            @if ($item->note)
                                <div class="mt-0.5 text-xs italic text-stone-500">Catatan: {{ $item->note }}</div>
                            @endif
                        </div>
                        <div class="shrink-0 font-semibold text-stone-700">{{ $money($item->subtotal) }}</div>
                    </li>
                @endforeach
            </ul>

            <dl class="mt-2 space-y-1 border-t border-matcha-100 pt-3 text-sm">
                <div class="flex justify-between"><dt class="text-stone-500">Subtotal</dt><dd>{{ $money($subtotal) }}</dd></div>
                @if ((int) $order->discount_amount > 0)
                    <div class="flex justify-between"><dt class="text-stone-500">Diskon</dt><dd>- {{ $money($order->discount_amount) }}</dd></div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-stone-500">Ongkir</dt>
                    <dd>{{ (int) $order->shipping_fee > 0 ? $money($order->shipping_fee) : 'Gratis' }}</dd>
                </div>
                <div class="flex justify-between border-t border-matcha-100 pt-2 text-base font-bold text-matcha-900">
                    <dt>Total</dt><dd class="text-matcha-700">{{ $money($order->total_amount) }}</dd>
                </div>
            </dl>
        </div>

        @if ($order->notes)
            <div class="card-soft p-5 text-sm">
                <h2 class="mb-1 text-sm font-bold text-matcha-900">Catatan Pesanan</h2>
                <p class="text-stone-600">{{ $order->notes }}</p>
            </div>
        @endif
    </div>

    <div class="space-y-4">
        {{-- Timeline --}}
        <div class="card-soft p-5">
            <h2 class="mb-3 text-sm font-bold text-matcha-900">Status Pesanan</h2>
            <ol class="space-y-4">
                @foreach ($timeline as $step)
                    @php
                        $tone = ! $step['done'] ? 'bg-matcha-100 text-matcha-700'
                            : (($step['bad'] ?? false) ? 'bg-red-600 text-white' : 'bg-matcha-700 text-white');
                    @endphp
                    <li class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm {{ $tone }}">
                            <i class="bi {{ $step['icon'] }}"></i>
                        </span>
                        <div>
                            <div class="text-sm {{ $step['done'] ? 'font-bold text-matcha-900' : 'text-stone-400' }}">{{ $step['label'] }}</div>
                            <div class="text-xs text-stone-500">{{ $step['done'] && $step['time'] ? $fmt($step['time']) : '—' }}</div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- Pembeli & pengiriman --}}
        <div class="card-soft p-5 text-sm">
            <h2 class="mb-2 text-sm font-bold text-matcha-900">Pembeli</h2>
            <div class="font-medium text-stone-800">{{ $order->customer_name }}</div>
            <div class="text-xs text-stone-500">{{ $order->customer_phone }}</div>

            <h2 class="mb-1 mt-4 text-sm font-bold text-matcha-900">
                {{ $isDelivery ? 'Pengiriman' : 'Pengambilan' }}
            </h2>
            <div class="text-xs text-stone-600">
                <i class="bi {{ $isDelivery ? 'bi-bicycle' : 'bi-bag-check' }}"></i>
                {{ $isDelivery ? 'Diantar' : 'Ambil sendiri' }}
            </div>
            <div class="mt-1 text-xs text-stone-500">{{ $order->shipping_address }}</div>
            @if ($isDelivery)
                <dl class="mt-2 space-y-1 text-xs text-stone-600">
                    @if ($order->shipping_courier)
                        <div class="flex justify-between gap-3"><dt class="text-stone-500">Kurir</dt>
                            <dd class="text-right">{{ strtoupper($order->shipping_courier) }} {{ $order->shipping_service }}</dd></div>
                    @endif
                    @if ($order->shipping_etd)
                        <div class="flex justify-between gap-3"><dt class="text-stone-500">Estimasi</dt><dd>{{ $order->shipping_etd }}</dd></div>
                    @endif
                    @if ($order->shipping_weight_grams)
                        <div class="flex justify-between gap-3"><dt class="text-stone-500">Berat</dt><dd>{{ number_format($order->shipping_weight_grams, 0, ',', '.') }} g</dd></div>
                    @endif
                    @if ($order->tracking_number)
                        <div class="flex justify-between gap-3"><dt class="text-stone-500">No. resi</dt><dd class="font-semibold">{{ $order->tracking_number }}</dd></div>
                    @endif
                </dl>
            @endif
        </div>

        {{-- Pembayaran --}}
        <div class="card-soft p-5 text-sm">
            <h2 class="mb-2 text-sm font-bold text-matcha-900">Pembayaran</h2>
            <dl class="space-y-1 text-xs text-stone-600">
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Metode</dt>
                    <dd><i class="bi {{ $order->payment_method_icon }}"></i> {{ $order->payment_method_label }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Status</dt>
                    <dd><span class="rounded-full px-2 py-0.5 font-bold {{ $paymentTone }}">{{ $order->payment_status_label }}</span></dd></div>
                @if ($order->payment?->payment_type)
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">Tipe</dt><dd>{{ $order->payment->payment_type }}</dd></div>
                @endif
                @if ($order->paid_at)
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">Dibayar</dt><dd>{{ $fmt($order->paid_at) }}</dd></div>
                @endif
            </dl>
        </div>
    </div>
</div>