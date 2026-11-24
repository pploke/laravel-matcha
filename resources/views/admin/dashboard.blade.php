<x-layouts.dashboard :nav="\App\Support\Navigation::admin()" role-label="Admin"
    title="Dashboard" subtitle="Ringkasan operasional gerai hari ini">
    <div class="w-full">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-card label="Omzet hari ini" value="Rp {{ number_format($todayRevenue, 0, ',', '.') }}"
                note="Selesai" icon="bi-cash-stack" />
            <x-stat-card label="Pesanan hari ini" :value="$todayOrders" note="Masuk" icon="bi-receipt" />
            <x-stat-card label="Perlu diproses" :value="$processing" note="Segera" tone="warn" icon="bi-hourglass-split" />
            <x-stat-card label="Selesai" :value="$completed" note="Total" tone="muted" icon="bi-check2-circle" />
        </div>

        @if ($newAlerts->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white">
                    <i class="bi bi-exclamation-lg"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-bold text-amber-900">Peringatan Stok Menipis</div>
                    <div class="mt-1 text-xs font-bold text-amber-900">Baru: {{ $newAlerts->join(', ') }}</div>
                </div>
                <a href="{{ route('admin.stock.index') }}" class="btn-matcha !py-2 no-underline">Lihat Stok</a>
            </div>
        @endif

        <div class="card-soft mt-4 p-5">
            <x-section-title title="Pintasan" desc="Menuju halaman yang paling sering dipakai." />
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['route' => 'admin.orders.index', 'icon' => 'bi-receipt', 'label' => 'Lihat pesanan masuk'],
                    ['route' => 'admin.products.index', 'icon' => 'bi-box-seam', 'label' => 'Kelola produk & menu'],
                    ['route' => 'admin.stock.index', 'icon' => 'bi-exclamation-triangle', 'label' => 'Cek stok menipis'],
                ] as $shortcut)
                    <a href="{{ route($shortcut['route']) }}"
                        class="flex items-center gap-3 rounded-xl border border-matcha-100 bg-matcha-50/60 p-3 text-sm font-semibold text-matcha-900 no-underline transition hover:border-matcha-400 hover:bg-white">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-matcha-700 text-white"><i class="bi {{ $shortcut['icon'] }}"></i></span>
                        {{ $shortcut['label'] }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="card-soft mt-4 p-5">
            <x-section-title title="Pesanan Terbaru" desc="Lima pesanan terakhir yang masuk.">
                <x-slot:action>
                    <a href="{{ route('admin.orders.index') }}" class="text-xs font-semibold text-matcha-700 no-underline hover:underline">
                        Lihat semua <i class="bi bi-arrow-right"></i>
                    </a>
                </x-slot:action>
            </x-section-title>

                <div class="overflow-x-auto">
                    <table class="table-matcha">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pembeli</th>
                                <th>Item</th>
                                <th>Total</th>
                                <th>Status Pesanan</th>
                                <th>Pembayaran</th> <!-- Kolom Baru -->
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentOrders as $order)
                                <tr>
                                    <td class="whitespace-nowrap font-semibold text-matcha-900">{{ $order->code }}</td>
                                    <td>{{ $order->customer_name }}</td>
                                    <td>{{ $order->items_count ?? $order->item_count }} menu</td>
                                    <td class="whitespace-nowrap">Rp {{ number_format((int) $order->total_amount, 0, ',', '.') }}</td>
                                    <td><x-status-badge :status="$order->order_status" /></td>
                                    <td>
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full 
                                            {{ in_array($order->payment_status, ['PAID', 'settlement', 'SETTLEMENT']) ? 'bg-emerald-100 text-emerald-800' : '' }}
                                            {{ in_array($order->payment_status, ['PENDING', 'UNPAID', '']) ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ in_array($order->payment_status, ['EXPIRED', 'CANCELLED', 'CANCEL', 'DENIED', 'deny']) ? 'bg-rose-100 text-rose-800' : '' }}">
                                            {{ $order->payment_status ?? 'UNPAID' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-stone-400">Belum ada pesanan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-soft p-5">
                <x-section-title title="Akses Cepat" desc="Menu yang sering dipakai admin." />
                <div class="space-y-2">
                    @foreach ([
                        ['route' => 'admin.orders.index', 'icon' => 'bi-receipt', 'label' => 'Lihat pesanan masuk'],
                        ['route' => 'admin.products.index', 'icon' => 'bi-box-seam', 'label' => 'Kelola produk & menu'],
                        ['route' => 'admin.stock.index', 'icon' => 'bi-clipboard-data', 'label' => 'Pantau stok persediaan'],
                    ] as $shortcut)
                        <a href="{{ route($shortcut['route']) }}"
                            class="flex items-center gap-3 rounded-xl border border-matcha-100 bg-white px-3 py-2.5 text-sm font-semibold text-matcha-800 no-underline transition hover:-translate-y-0.5 hover:border-matcha-300 hover:shadow-soft">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-matcha-100 text-matcha-700">
                                <i class="bi {{ $shortcut['icon'] }}"></i>
                            </span>
                            {{ $shortcut['label'] }}
                            <i class="bi bi-chevron-right ml-auto text-xs text-stone-400"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-layouts.dashboard>