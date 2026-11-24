@props(['status' => '', 'label' => null])

@php
    $map = [
        'Aktif' => 'green',
        'Aman' => 'green',
        'Dibayar' => 'green',
        'Selesai' => 'green',
        'COMPLETED' => 'green',
        'Diproses' => 'amber',
        'PROCESSING' => 'amber',
        'Menunggu' => 'amber',
        'Menunggu Pembayaran' => 'amber',
        'PENDING_PAYMENT' => 'amber',
        'Menipis' => 'amber',
        'Dikirim' => 'amber',
        'Siap Diambil' => 'amber',
        'Dibatalkan' => 'red',
        'CANCELLED' => 'red',
        'Habis' => 'red',
        'Nonaktif' => 'red',
        'Gagal' => 'red',
    ];
    $tones = [
        'green' => 'bg-matcha-700 text-white',
        'amber' => 'bg-amber-600 text-white',
        'red' => 'bg-red-600 text-white',
    ];
    $text = $label ?? (\App\Models\Order::STATUS_LABELS[$status] ?? $status);
    $tone = $tones[$map[$status] ?? 'green'];
@endphp

<span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $tone }}">{{ $text }}</span>
