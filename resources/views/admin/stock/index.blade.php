<x-layouts.dashboard :nav="\App\Support\Navigation::admin()" role-label="Admin"
    title="Stok & Persediaan" subtitle="Pantau ketersediaan agar tidak kehabisan">
    <div class="w-full">
        @if ($stats['low'] > 0 || $stats['out'] > 0)
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white">
                    <i class="bi bi-exclamation-lg"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-bold text-amber-900">Peringatan Stok Menipis</div>
                    <div class="text-xs text-amber-800">
                        {{ $stats['low'] }} varian menipis dan {{ $stats['out'] }} varian habis. Perbarui stok lewat halaman produk.
                    </div>
                </div>
                <a href="{{ route('admin.products.index') }}" class="btn-matcha !py-2 no-underline">
                    <i class="bi bi-box-seam"></i> Kelola di Produk
                </a>
            </div>
        @endif

        <div class="mb-4 grid gap-4 md:grid-cols-3">
            <x-stat-card label="Item menipis" :value="$stats['low']" note="Perlu restock" tone="warn" icon="bi-exclamation" />
            <x-stat-card label="Stok habis" :value="$stats['out']" tone="muted" icon="bi-x-circle" />
            <x-stat-card label="Total item aktif" :value="$stats['total']" note="{{ $stats['safe'] }} aman" icon="bi-clipboard-data" />
        </div>

        <div class="card-soft p-5">
            <x-section-title title="Daftar Persediaan" desc="Stok bahan dan perlengkapan beserta batas minimumnya." />

            <div class="overflow-x-auto">
                <table class="table-matcha">
                    <thead>
                        <tr>
                            <th>Nama persediaan</th>
                            <th>Stok saat ini</th>
                            <th>Batas minimum</th>
                            <th>Status</th>
                            <th>Terakhir diperbarui</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php($variant = $row['variant'])
                            <tr>
                                <td>
                                    <div class="font-semibold text-matcha-900">{{ $variant->product?->name ?? 'Produk dihapus' }}</div>
                                    <div class="text-xs text-stone-500">{{ $variant->variant_name }}</div>
                                </td>
                                <td class="whitespace-nowrap">{{ $variant->stock }} pcs</td>
                                <td class="whitespace-nowrap">{{ $row['threshold'] }} pcs</td>
                                <td><x-status-badge :status="$row['status']" /></td>
                                <td class="whitespace-nowrap text-xs">{{ $variant->updated_at?->format('d M Y, H:i') }}</td>
                                <td>
                                    @if ($variant->product)
                                        <a href="{{ route('admin.products.edit', $variant->product) }}" class="text-xs font-semibold text-matcha-700 hover:underline">
                                            Edit stok
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-stone-400">Belum ada varian produk.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
