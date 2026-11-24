<x-layouts.dashboard :nav="\App\Support\Navigation::admin()" role-label="Admin"
    title="Pengeluaran" :subtitle="'Catatan dan rekap pengeluaran · ' . $monthLabel">

    <div class="w-full space-y-4">

        {{-- Ringkasan --}}
        <div class="grid gap-3 sm:grid-cols-3">
            <x-stat-card label="Total Pengeluaran" :value="'Rp ' . number_format($monthTotal, 0, ',', '.')"
                :note="$monthLabel" tone="muted" icon="bi-wallet2" />
            <x-stat-card label="Kategori Terbesar" :value="$topCategory?->category ?? '-'"
                :note="$topCategory ? 'Rp ' . number_format($topCategory->total, 0, ',', '.') : null"
                tone="warn" icon="bi-pie-chart" />
            <x-stat-card label="Jumlah Catatan" :value="$byCategory->sum('count')" note="Bulan ini" icon="bi-journal-text" />
        </div>

        {{-- Filter --}}
        <div class="card-soft p-5">
            <form action="{{ route('admin.expenses.index') }}" method="GET" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="month" class="label-matcha">Bulan</label>
                    <input type="month" id="month" name="month" value="{{ $monthValue }}" class="input-matcha">
                </div>
                <div>
                    <label for="category" class="label-matcha">Kategori</label>
                    <select id="category" name="category" class="select-matcha">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $item)
                            <option value="{{ $item }}" @selected($category === $item)>{{ $item }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-matcha !py-2"><i class="bi bi-search"></i> Tampilkan</button>
                <a href="{{ route('admin.expenses.index') }}" class="btn-outline-matcha !py-2 no-underline">Bulan Ini</a>
                <a href="{{ route('admin.expenses.create') }}" class="btn-matcha ml-auto no-underline">
                    <i class="bi bi-plus-lg"></i> Catat Pengeluaran
                </a>
            </form>
        </div>

        {{-- Rekap per kategori --}}
        <div class="card-soft p-5">
            <x-section-title title="Rekap Per Kategori"
                desc="Total pengeluaran per kategori pada {{ $monthLabel }}." />

            @php $maxTotal = max((int) $byCategory->max('total'), 1); @endphp

            <div class="mt-4 space-y-3">
                @forelse ($byCategory as $row)
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="font-semibold text-matcha-900">
                                {{ $row->category }}
                                <span class="font-normal text-stone-500">· {{ $row->count }} catatan</span>
                            </span>
                            <span class="font-semibold text-matcha-900">
                                Rp {{ number_format($row->total, 0, ',', '.') }}
                                <span class="font-normal text-stone-500">
                                    ({{ round(($row->total / max($monthTotal, 1)) * 100) }}%)
                                </span>
                            </span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-matcha-100">
                            <div class="h-2 rounded-full" style="width: {{ ($row->total / $maxTotal) * 100 }}%; background-color: #6f8f54;"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Belum ada pengeluaran pada bulan ini.</p>
                @endforelse
            </div>

            @if ($byCategory->isNotEmpty())
                <div class="mt-4 flex justify-between border-t border-matcha-100 pt-3 text-sm font-bold text-matcha-900">
                    <span>Total</span>
                    <span>Rp {{ number_format($monthTotal, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>

        {{-- Daftar --}}
        <div class="card-soft p-5">
            <x-section-title title="Daftar Pengeluaran"
                desc="Urut dari tanggal terbaru{{ $category ? ' · kategori ' . $category : '' }}." />

            <div class="mt-4 overflow-x-auto">
                <table class="table-matcha">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Judul</th>
                            <th>Kategori</th>
                            <th>Nominal</th>
                            <th>Dicatat oleh</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            <tr>
                                <td class="whitespace-nowrap">{{ $expense->expense_date->locale('id')->translatedFormat('d M Y') }}</td>
                                <td>
                                    <div class="font-semibold text-matcha-900">{{ $expense->title }}</div>
                                    @if ($expense->notes)
                                        <div class="max-w-xs truncate text-xs text-stone-500">{{ $expense->notes }}</div>
                                    @endif
                                </td>
                                <td>{{ $expense->category }}</td>
                                <td class="whitespace-nowrap">Rp {{ number_format($expense->amount, 0, ',', '.') }}</td>
                                <td>{{ $expense->user?->name ?? '-' }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.expenses.edit', $expense) }}"
                                            class="btn-outline-matcha !px-3 !py-1 text-xs no-underline">
                                            <i class="bi bi-pencil"></i> Ubah
                                        </a>
                                        <form method="POST" action="{{ route('admin.expenses.destroy', $expense) }}" class="inline"
                                            data-confirm="Hapus pengeluaran ini? Tindakan ini tidak bisa dibatalkan.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-outline-matcha !px-3 !py-1 text-xs">
                                                <i class="bi bi-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-stone-500">Tidak ada data pengeluaran.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $expenses->links() }}</div>
        </div>
    </div>
</x-layouts.dashboard>