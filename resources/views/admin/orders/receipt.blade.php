@php
    $money = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
    $subtotal = (int) ($order->subtotal ?: ((int) $order->total_amount - (int) $order->shipping_fee + (int) $order->discount_amount));
    $paid = $order->payment_status === 'PAID';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $order->code }} · Matcha and Me</title>
    {{-- CSS ditulis inline agar struk tetap rapi saat dicetak, tanpa tergantung Vite. --}}
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f1; font-family: "Courier New", ui-monospace, monospace; font-size: 12px; color: #111; }
        .toolbar { display: flex; justify-content: center; gap: 8px; padding: 12px; }
        .toolbar a, .toolbar button { font: 600 12px system-ui, sans-serif; padding: 8px 14px; border-radius: 8px; border: 1px solid #4d7c0f; cursor: pointer; text-decoration: none; }
        .toolbar button { background: #4d7c0f; color: #fff; }
        .toolbar a { background: #fff; color: #4d7c0f; }
        .receipt { width: 80mm; margin: 0 auto 24px; padding: 6mm 5mm; background: #fff; box-shadow: 0 1px 6px rgba(0,0,0,.12); }
        .center { text-align: center; }
        .brand { font-size: 15px; font-weight: 700; }
        hr { border: 0; border-top: 1px dashed #555; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .item { margin-bottom: 6px; }
        .muted { color: #555; font-size: 11px; }
        .total { font-size: 14px; font-weight: 700; }
        .stamp { display: inline-block; border: 1.5px solid #111; padding: 1px 8px; font-weight: 700; margin-top: 4px; }
        @page { size: 80mm auto; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .receipt { box-shadow: none; margin: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak Struk</button>
        <a href="{{ $backUrl }}">Kembali</a>
    </div>

    <div class="receipt">
        <div class="center">
            <div class="brand">MATCHA AND ME</div>
            <div class="muted">Struk Pesanan</div>
        </div>
        <hr>
        <div class="row"><span>Kode</span><span>{{ $order->code }}</span></div>
        <div class="row"><span>Tanggal</span><span>{{ $order->created_at?->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Pembeli</span><span>{{ $order->customer_name }}</span></div>
        <div class="row"><span>Jenis</span><span>{{ $order->delivery_type === 'ambil' ? 'Ambil sendiri' : 'Diantar' }}</span></div>
        <div class="row"><span>Bayar</span><span>{{ $order->payment_method_label }}</span></div>
        <hr>

        @foreach ($order->items as $item)
            <div class="item">
                <div>{{ $item->product?->name ?? 'Produk dihapus' }}</div>
                <div class="muted">
                    {{ $item->variant?->variant_name ?? 'Tanpa varian' }}@if ($item->sweetness), gula {{ $item->sweetness }}@endif
                </div>
                @if ($item->note)<div class="muted">Cat: {{ $item->note }}</div>@endif
                <div class="row">
                    <span>{{ $item->quantity }} x {{ number_format((int) $item->unit_price, 0, ',', '.') }}</span>
                    <span>{{ number_format($item->subtotal, 0, ',', '.') }}</span>
                </div>
            </div>
        @endforeach
        <hr>

        <div class="row"><span>Subtotal</span><span>{{ $money($subtotal) }}</span></div>
        @if ((int) $order->discount_amount > 0)
            <div class="row"><span>Diskon</span><span>- {{ $money($order->discount_amount) }}</span></div>
        @endif
        <div class="row">
            <span>Ongkir</span>
            <span>{{ (int) $order->shipping_fee > 0 ? $money($order->shipping_fee) : 'Gratis' }}</span>
        </div>
        <div class="row total"><span>TOTAL</span><span>{{ $money($order->total_amount) }}</span></div>
        <hr>

        <div class="center">
            <span class="stamp">{{ $paid ? 'LUNAS' : 'BELUM LUNAS' }}</span>
            @if ($paid && $order->paid_at)
                <div class="muted">Dibayar {{ $order->paid_at->format('d/m/Y H:i') }}</div>
            @endif
            <div class="muted" style="margin-top:8px">Terima kasih sudah memesan!</div>
        </div>
    </div>
</body>
</html>