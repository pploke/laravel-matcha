<x-layouts.dashboard :nav="\App\Support\Navigation::admin()" role-label="Admin"
    title="Daftar Pesanan" subtitle="Perbarui status pesanan agar pembeli mendapat informasi terbaru">
    <div class="w-full">
        <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-card label="Total pesanan" :value="$stats['total']" icon="bi-receipt" />
            <x-stat-card label="Perlu diproses" :value="$stats['processing']" note="Segera" tone="warn" icon="bi-hourglass-split" />
            <x-stat-card label="Selesai" :value="$stats['completed']" icon="bi-check2-circle" />
            <x-stat-card label="Dibatalkan" :value="$stats['cancelled']" tone="muted" icon="bi-x-circle" />
        </div>

        <div class="card-soft p-5">
            <x-section-title title="Pesanan Masuk" desc="Perbarui status pesanan agar pembeli mendapat informasi terbaru." />

            <form method="GET" action="{{ route('admin.orders.index') }}" class="mb-3 flex flex-wrap items-center gap-3">
                <select name="status" class="select-matcha" onchange="this.form.submit()">
                    <option value="">Semua status</option>
                    @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="relative ml-auto">
                    <i class="bi bi-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-stone-400"></i>
                    <input name="q" value="{{ $q }}" placeholder="Cari pesanan..."
                        class="w-52 rounded-lg border border-matcha-200 bg-white py-1.5 pl-8 pr-3 text-sm outline-none focus:border-matcha-500">
                </div>
                <button type="submit" class="btn-matcha !py-1.5 text-xs">Terapkan</button>
            </form>

            <div class="overflow-x-auto">
                <table class="table-matcha">
                    <thead>
                        <tr>
                            <th>ID Pesanan</th>
                            <th>Pembeli</th>
                            <th>Jenis</th>
                            <th>Metode Bayar</th>
                            <th>Waktu</th>
                            <th>Item</th>
                            <th>Total</th>
                            <th>Status Pesanan</th>
                            <th>Status Pembayaran</th> <!-- Kolom Baru (3.2.12) -->
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                        <tr>
                            <td class="whitespace-nowrap font-semibold text-matcha-900">{{ $order->code }}</td>
                            <td>
                                <div class="font-medium text-stone-800">{{ $order->customer_name }}</div>
                                <div class="text-xs text-stone-500">{{ $order->customer_phone }}</div>
                                @if ($order->user)
                                <div class="text-[11px] text-matcha-600">Akun: {{ $order->user->email }}</div>
                                @else
                                <div class="text-[11px] text-stone-400">Tamu</div>
                                @endif
                            </td>
                            <td>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-matcha-800">
                                    <i class="bi {{ $order->delivery_type === 'ambil' ? 'bi-bag-check' : 'bi-bicycle' }}"></i>
                                    {{ $order->delivery_type === 'ambil' ? 'Ambil sendiri' : 'Diantar' }}
                                </span>
                            </td>
                            <td>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-stone-700">
                                    <i class="bi {{ $order->payment_method_icon }}"></i>
                                    {{ $order->payment_method_label }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-xs">{{ $order->created_at?->format('d M Y, H:i') }}</td>
                            <td>{{ $order->item_count }} menu</td>
                            <td class="whitespace-nowrap">Rp {{ number_format((int) $order->total_amount, 0, ',', '.') }}</td>
                            <td><x-status-badge :status="$order->order_status" /></td>

                            <!-- Badge Status Pembayaran Admin -->
                            <td>
                                @php
                                $paymentTone = match (true) {
                                $order->payment_status === 'PAID' => 'bg-emerald-100 text-emerald-800',
                                in_array($order->payment_status, ['EXPIRED', 'CANCELLED', 'DENIED', 'REFUNDED'], true) => 'bg-rose-100 text-rose-800',
                                default => 'bg-amber-100 text-amber-800',
                                };
                                @endphp
                                <span class="px-2.5 py-1 text-xs font-bold rounded-full {{ $paymentTone }}">
                                    {{ $order->payment_status_label }}
                                </span>
                                @if ($order->order_status === 'CANCELLED' && $order->payment_status === 'PAID')
                                    <div class="mt-1 text-[10px] font-bold text-amber-700">Perlu refund</div>
                                @endif
                            </td>

                            <td class="whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if($order->isCashPayment() && $order->payment_status !== 'PAID' && $order->order_status !== 'CANCELLED')
                                    <form method="POST" action="{{ route('admin.orders.paid', $order) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                            <i class="bi bi-cash-coin mr-1"></i> Tandai Lunas
                                        </button>
                                    </form>
                                    @endif

                                    <a href="{{ route('admin.orders.show', $order) }}"
                                        class="rounded-lg border border-matcha-200 px-2.5 py-1 text-xs font-semibold text-matcha-700 no-underline transition hover:bg-matcha-50">
                                        <i class="bi bi-eye mr-1"></i> Detail
                                    </a>

                                    @if($order->order_status === 'PROCESSING' && $order->payment_status === 'PAID')
                                    <form method="POST" action="{{ route('admin.orders.status', $order) }}"
                                        data-confirm="Tandai pesanan {{ $order->code }} selesai?">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="COMPLETED">
                                        <button type="submit" class="btn-matcha !px-2.5 !py-1 text-xs">
                                            <i class="bi bi-check2-circle"></i> Selesaikan
                                        </button>
                                    </form>
                                    @endif
                                </div>

                                <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mt-1 flex items-center gap-1.5">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="input-matcha !w-auto !px-2 !py-1 text-xs">
                                        @foreach (['PROCESSING', 'COMPLETED', 'CANCELLED'] as $statusOption)
                                        <option value="{{ $statusOption }}" @selected($order->order_status === $statusOption)
                                            @disabled($statusOption === 'COMPLETED' && $order->payment_status !== 'PAID')>
                                            {{ $statusLabels[$statusOption] }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-matcha !px-2.5 !py-1 text-xs">Simpan</button>
                                </form>
                                @if($order->payment_status !== 'PAID' && $order->order_status !== 'CANCELLED')
                                    <p class="mt-1 text-[11px] text-stone-400">Status Selesai aktif setelah pesanan lunas.</p>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center text-stone-400">Tidak ada pesanan yang cocok.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modal detail per pesanan --}}
        @foreach ($orders as $order)
            <dialog id="order-{{ $order->id }}" class="modal-matcha">
                <div class="flex items-center justify-between">
                    <h5 class="text-base font-bold text-matcha-900">Pesanan {{ $order->code }}</h5>
                    <button type="button" aria-label="Tutup" data-close-dialog class="rounded-lg p-1 text-stone-500 hover:bg-matcha-50"><i class="bi bi-x-lg"></i></button>
                </div>

                <div class="mt-3 text-sm">
                    @if ($order->order_status === 'CANCELLED' && $order->payment_status === 'PAID')
                        <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            <b>Perlu refund:</b> pembayaran diterima setelah pesanan dibatalkan/kedaluwarsa. Stok tidak dipotong ulang.
                        </div>
                    @endif
                    <dl class="mb-4 space-y-1.5">
                        <div class="flex justify-between"><dt class="text-stone-500">Pembeli</dt><dd>{{ $order->customer_name }} · {{ $order->customer_phone }}</dd></div>
                        <div class="flex justify-between"><dt class="text-stone-500">Jenis</dt><dd>{{ $order->delivery_type === 'ambil' ? 'Ambil sendiri' : 'Diantar' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-stone-500">Status</dt><dd><x-status-badge :status="$order->order_status" /></dd></div>
                        <div class="flex justify-between"><dt class="text-stone-500">Waktu</dt><dd>{{ $order->created_at?->format('d M Y, H:i') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-stone-500">Item</dt><dd>{{ $order->item_count }} menu</dd></div>
                        <div class="flex justify-between"><dt class="text-stone-500">Total</dt><dd class="font-bold">Rp {{ number_format((int) $order->total_amount, 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="shrink-0 text-stone-500">Alamat</dt><dd class="text-right">{{ $order->shipping_address }}</dd></div>
                    </dl>

                    <ul class="mb-4 space-y-1 rounded-xl bg-matcha-50/70 p-3 text-xs text-stone-600">
                        @foreach ($order->items as $item)
                            <li class="flex justify-between gap-2">
                                <span>{{ $item->quantity }}× {{ $item->product?->name ?? 'Produk dihapus' }}
                                    <span class="text-stone-400">({{ $item->variant?->variant_name ?? 'tanpa varian' }})</span></span>
                                <span class="shrink-0">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                        @csrf
                        @method('PATCH')
                        <label class="label-matcha !text-xs" for="status-{{ $order->id }}">Ubah status</label>
                        <select id="status-{{ $order->id }}" name="status" class="input-matcha">
                            @foreach (['PROCESSING', 'COMPLETED', 'CANCELLED'] as $statusOption)
                                <option value="{{ $statusOption }}" @selected($order->order_status === $statusOption)>{{ $statusLabels[$statusOption] }}</option>
                            @endforeach
                        </select>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" class="btn-outline-matcha" data-close-dialog>Tutup</button>
                            <button type="submit" class="btn-matcha">Simpan Status</button>
                        </div>
                    </form>
                </div>
            </dialog>
        @endforeach
    </div>
</x-layouts.dashboard>