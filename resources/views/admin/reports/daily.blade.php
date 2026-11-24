<x-layouts.dashboard :nav="\App\Support\Navigation::admin()" role-label="Admin"
    title="Laporan Penjualan" subtitle="Rekap penjualan gerai Telang berdasarkan tanggal">

    <div class="w-full space-y-4">

        {{-- Filter tanggal --}}
        <!-- <div class="card-soft p-5">
            <form action="{{ route('admin.reports.daily') }}" method="GET"
                class="flex flex-wrap items-end gap-3">

                <div>
                    <label for="date" class="mb-1 block text-sm font-semibold text-matcha-800">
                        Pilih Tanggal Penjualan
                    </label>

                    <input type="date" id="date" name="date"
                        value="{{ $date->toDateString() }}"
                        class="rounded-xl border border-matcha-200 px-3 py-2 text-sm">
                </div>

                <button type="submit" class="btn-matcha !py-2">
                    <i class="bi bi-search"></i> Tampilkan
                </button>

                <a href="{{ route('admin.reports.daily') }}"
                    class="rounded-xl border border-matcha-200 px-4 py-2 text-sm font-semibold text-matcha-800 no-underline">
                    Hari Ini
                </a>
            </form>
        </div> -->

        {{-- Kontrol periode --}}
        <x-report-period-tabs active="daily" :value="$date->toDateString()" />

        {{-- Ringkasan penjualan --}}
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">

            <x-stat-card
                label="Total Omzet"
                :value="'Rp ' . number_format($summary['total_revenue'], 0, ',', '.')"
                note="Penjualan selesai"
                icon="bi-cash-stack" />

            <x-stat-card
                label="Jumlah Transaksi"
                :value="$summary['total_transactions']"
                note="Transaksi selesai"
                icon="bi-receipt" />

            <x-stat-card
                label="Produk Terjual"
                :value="$summary['total_items_sold']"
                note="Total item"
                icon="bi-box-seam" />

            <x-stat-card
                label="Rata-rata Transaksi"
                :value="'Rp ' . number_format($summary['average_transaction'], 0, ',', '.')"
                note="Per transaksi"
                icon="bi-calculator" />

        </div>

        
        {{-- Grafik penjualan per jam --}}
        @php
            $maxHourlyRevenue = max($hourlySales->max('total_revenue'), 1);
        @endphp

        <div class="card-soft p-5">
            <x-section-title
                title="Grafik Penjualan Per Jam"
                desc="Omzet penjualan berdasarkan waktu pesanan selesai." />

            <div class="mt-6 overflow-x-auto">
                <div class="flex h-56 min-w-[700px] items-end gap-2 border-b border-matcha-200 pb-2">

                    @foreach ($hourlySales as $hour)
                        @php
                            $barHeight = $hour['total_revenue'] > 0
                                ? max(6, ($hour['total_revenue'] / $maxHourlyRevenue) * 160)
                                : 0;
                        @endphp

                        <div
                            class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2"
                            title="{{ $hour['label'] }} | Rp {{ number_format($hour['total_revenue'], 0, ',', '.') }} | {{ $hour['total_transactions'] }} transaksi">

                            <div class="flex h-[180px] items-end justify-center">
                                <div
                                    class="w-full max-w-8 rounded-t-md"
                                    style="height: {{ $barHeight }}px; background-color: #6f8f54;">
                                </div>
                            </div>

                            <span class="text-center text-[10px] text-stone-500">
                                {{ $hour['hour'] % 2 === 0 ? substr($hour['label'], 0, 2) : '' }}
                            </span>

                        </div>
                    @endforeach

                </div>

                <p class="mt-3 text-xs text-stone-500">
                    Sumbu horizontal menunjukkan jam (00–23), sedangkan tinggi batang menunjukkan omzet penjualan.
                    Arahkan kursor ke batang untuk melihat detail omzet dan jumlah transaksi.
                </p>
            </div>
        </div>


        {{-- Tabel transaksi --}}
        <div class="card-soft p-5">
            <x-section-title
                title="Detail Transaksi"
                desc="Daftar pesanan yang selesai pada tanggal terpilih." />

            <div class="mt-4 overflow-x-auto">
                <table class="table-matcha">
                    <thead>
                        <tr>
                            <th>ID Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Jumlah Item</th>
                            <th>Total Pembayaran</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($transactions as $order)
                            <tr>
                                <td class="whitespace-nowrap font-semibold text-matcha-900">
                                    {{ $order->code }}
                                </td>

                                <td>{{ $order->customer_name }}</td>

                                <td>{{ $order->items->sum('quantity') }} menu</td>

                                <td class="whitespace-nowrap">
                                    Rp {{ number_format((int) $order->total_amount, 0, ',', '.') }}
                                </td>

                                <td>
                                    <x-status-badge :status="$order->order_status" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-stone-400">
                                    Belum ada transaksi selesai pada tanggal ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $transactions->links() }}
            </div>
        </div>

        {{-- Produk terjual --}}
        <div class="card-soft p-5">
            <x-section-title
                title="Menu Terjual"
                desc="Jumlah produk dan varian yang terjual pada tanggal terpilih." />

            <div class="mt-4 overflow-x-auto">
                <table class="table-matcha">
                    <thead>
                        <tr>
                            <th>Nama Produk</th>
                            <th>Varian</th>
                            <th>Jumlah Terjual</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($productsSold as $product)
                            <tr>
                                <td class="font-semibold text-matcha-900">
                                    {{ $product->product_name }}
                                </td>

                                <td>{{ $product->variant_name ?? '-' }}</td>

                                <td>{{ $product->quantity_sold }} item</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-8 text-center text-stone-400">
                                    Belum ada produk terjual pada tanggal ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-layouts.dashboard>
