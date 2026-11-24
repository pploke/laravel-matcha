<x-layouts.dashboard :nav="\App\Support\Navigation::admin()" role-label="Admin"
    :title="$title" :subtitle="'Rekap penjualan gerai Telang · ' . $periodLabel">

    <div class="w-full space-y-4">

        {{-- Kontrol periode (dipakai bersama halaman harian/mingguan/bulanan) --}}
        <x-report-period-tabs :active="$period" :value="$inputValue" />

        {{-- Ringkasan --}}
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                label="Total Omzet"
                :value="'Rp ' . number_format($report['total_revenue'], 0, ',', '.')"
                note="Penjualan selesai"
                icon="bi-cash-stack" />

            <x-stat-card
                label="Jumlah Transaksi"
                :value="$report['total_transactions']"
                note="Transaksi selesai"
                icon="bi-receipt" />

            <x-stat-card
                label="Produk Terjual"
                :value="$report['total_items_sold']"
                note="Total item"
                icon="bi-box-seam" />

            <x-stat-card
                label="Rata-rata Transaksi"
                :value="'Rp ' . number_format($report['average_transaction'], 0, ',', '.')"
                note="Per transaksi"
                icon="bi-calculator" />
        </div>

        {{-- Grafik tren per hari --}}
        @php
            $maxRevenue = max($report['days']->max('total_revenue'), 1);
        @endphp

        <div class="card-soft p-5">
            <x-section-title
                title="Tren Penjualan Per Hari"
                desc="Omzet harian untuk periode {{ $periodLabel }}." />

            <div class="mt-6 overflow-x-auto">
                <div class="flex h-56 min-w-[700px] items-end gap-2 border-b border-matcha-200 pb-2">
                    @foreach ($report['days'] as $row)
                        @php
                            $barHeight = $row['total_revenue'] > 0
                                ? max(6, ($row['total_revenue'] / $maxRevenue) * 160)
                                : 0;
                        @endphp

                        <div class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2"
                            title="{{ \Carbon\Carbon::parse($row['date'])->locale('id')->translatedFormat('d F Y') }} | Rp {{ number_format($row['total_revenue'], 0, ',', '.') }} | {{ $row['total_transactions'] }} transaksi">
                            <div class="flex h-[180px] items-end justify-center">
                                <div class="w-full max-w-8 rounded-t-md"
                                    style="height: {{ $barHeight }}px; background-color: #6f8f54;"></div>
                            </div>
                            <span class="text-center text-[10px] text-stone-500">{{ $row['day'] }}</span>
                        </div>
                    @endforeach
                </div>

                <p class="mt-3 text-xs text-stone-500">
                    Sumbu horizontal menunjukkan tanggal, tinggi batang menunjukkan omzet.
                    Arahkan kursor ke batang untuk melihat detail.
                </p>
            </div>
        </div>

        {{-- Tabel rincian per hari --}}
        <div class="card-soft p-5">
            <x-section-title
                title="Rincian Per Hari"
                desc="Total di baris paling bawah sama dengan kartu ringkasan di atas." />

            <div class="mt-4 overflow-x-auto">
                <table class="table-matcha">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Transaksi</th>
                            <th>Item Terjual</th>
                            <th>Omzet</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($report['days'] as $row)
                            <tr class="{{ $row['total_transactions'] === 0 ? 'text-stone-400' : '' }}">
                                <td class="whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($row['date'])->locale('id')->translatedFormat('l, d F Y') }}
                                </td>
                                <td>{{ $row['total_transactions'] }}</td>
                                <td>{{ $row['total_items_sold'] }}</td>
                                <td class="whitespace-nowrap">
                                    Rp {{ number_format($row['total_revenue'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr class="font-bold text-matcha-900">
                            <td>Total</td>
                            <td>{{ $report['total_transactions'] }}</td>
                            <td>{{ $report['total_items_sold'] }}</td>
                            <td class="whitespace-nowrap">
                                Rp {{ number_format($report['total_revenue'], 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>
</x-layouts.dashboard>