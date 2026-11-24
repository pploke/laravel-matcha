<x-layouts.dashboard :nav="\App\Support\Navigation::konsumen()" role-label="Konsumen"
    title="Keranjang Belanja" subtitle="Periksa pilihan menu sebelum melanjutkan pembayaran">
    <div class="w-full">
        <div class="grid gap-6 lg:grid-cols-12">
            <div class="lg:col-span-8">
                <div class="card-soft p-5 sm:p-6">
                    <h2 class="text-base font-bold text-matcha-900">Keranjang Saya</h2>
                    <p class="text-xs text-stone-500">Ubah jumlah atau hapus menu sebelum melanjutkan pembayaran.</p>

                    @if (empty($rows))
                        <div class="py-14 text-center">
                            <i class="bi bi-bag text-4xl text-matcha-300"></i>
                            <p class="mt-2 text-sm text-stone-500">Keranjang kamu masih kosong.</p>
                            <a href="{{ route('katalog.index') }}" class="btn-matcha mt-4 no-underline">Lihat Katalog Menu</a>
                        </div>
                    @else
                        <ul class="mt-4 divide-y divide-matcha-100">
                            @foreach ($rows as $row)
                                <li class="flex items-center gap-4 py-4">
                                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl">
                                        <x-drink-image :src="$row['image']" :tone="$row['tone']" :alt="$row['name']" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold text-matcha-900">{{ $row['name'] }}</div>
                                        <div class="text-xs text-stone-500">
                                            {{ $row['variant_name'] ?? 'Tanpa varian' }} • Manis {{ $row['sweet'] }}@if (! empty($row['note'])) • {{ $row['note'] }}@endif
                                            @if ($row['stock'] !== null) • Stok {{ $row['stock'] }}@endif
                                        </div>
                                        <div class="mt-1 text-sm font-bold text-matcha-700">Rp {{ number_format((int) $row['unit_price'], 0, ',', '.') }}</div>
                                    </div>

                                    {{-- Stepper jumlah (mengirim PATCH ke cart.update) --}}
                                    <form method="POST" action="{{ route('cart.update', $row['row_id']) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="qty" value="{{ $row['qty'] }}">
                                        <button type="button" aria-label="Kurangi" data-qty-step="-1" @disabled($row['qty'] <= 1)
                                            class="flex h-7 w-7 items-center justify-center rounded-full border border-matcha-200 text-matcha-700 hover:bg-matcha-50 disabled:cursor-not-allowed disabled:opacity-40">
                                            <i class="bi bi-dash text-sm"></i>
                                        </button>
                                        <span class="w-5 text-center text-sm font-semibold">{{ $row['qty'] }}</span>
                                        <button type="button" aria-label="Tambah" data-qty-step="1" data-qty-max="{{ $row['stock'] ?? 99 }}"
                                            @disabled($row['qty'] >= ($row['stock'] ?? 99))
                                            class="flex h-7 w-7 items-center justify-center rounded-full border border-matcha-200 text-matcha-700 hover:bg-matcha-50 disabled:cursor-not-allowed disabled:opacity-40">
                                            <i class="bi bi-plus text-sm"></i>
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('cart.remove', $row['row_id']) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Hapus</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ route('katalog.index') }}" class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-matcha-700 no-underline hover:text-matcha-900">
                            <i class="bi bi-plus-lg"></i> Tambah menu lain
                        </a>
                    @endif
                </div>
            </div>

            <div class="lg:col-span-4">
                <div class="card-soft p-5">
                    <h3 class="text-sm font-bold text-matcha-900">Ringkasan Pesanan</h3>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-stone-500">Subtotal</dt>
                            <dd class="font-semibold">Rp {{ number_format((int) ($subtotal ?? 0), 0, ',', '.') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-stone-500">Ongkir</dt>
                            <dd class="text-xs font-semibold text-stone-600">Dihitung saat checkout (jika diantar)</dd>
                        </div>
                    </dl>
                    <div class="mt-4 flex items-center justify-between border-t border-matcha-100 pt-4">
                        <span class="text-sm font-semibold text-matcha-900">Total sementara</span>
                        <span class="text-lg font-extrabold text-matcha-700">Rp {{ number_format((int) ($subtotal ?? 0), 0, ',', '.') }}</span>
                    </div>
                    @if (empty($rows))
                        <button type="button" disabled class="btn-matcha mt-4 w-full">Lanjut Checkout</button>
                    @else
                        <a href="{{ route('checkout.index') }}" class="btn-matcha mt-4 w-full no-underline">Lanjut Checkout</a>
                    @endif
                    <p class="mt-3 text-[11px] text-stone-400"><i class="bi bi-shield-check mr-1"></i>Pembayaran aman &amp; terverifikasi</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
