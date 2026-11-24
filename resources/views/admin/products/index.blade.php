@php
    $tagOf = fn ($p) => $p->getAttributes()['tag'] ?? $p->tag;
    $tags = $products->map($tagOf)->filter()->unique()->sort()->values();
@endphp

<x-layouts.dashboard :nav="\App\Support\Navigation::admin()" role-label="Admin"
    title="Produk & Menu" subtitle="Kelola katalog, harga, varian, dan stok">
    <div class="w-full">
        <div class="card-soft p-5">
            <x-section-title title="Daftar Produk" desc="Informasi menu yang tersedia di katalog penjual.">
                <x-slot:action>
                    <a href="{{ route('admin.products.create') }}" class="btn-matcha no-underline">
                        <i class="bi bi-plus-lg"></i> Tambah Produk
                    </a>
                </x-slot:action>
            </x-section-title>

            <div class="mb-4 grid gap-4 md:grid-cols-3">
                <x-stat-card label="Total produk" :value="$stats['total']" :note="$tags->count().' kategori'" tone="muted" />
                <x-stat-card label="Produk aktif" :value="$stats['active']" :note="round(($stats['active'] / max($stats['total'], 1)) * 100).'%'" />
                <x-stat-card label="Stok perlu perhatian" :value="$stats['attention']" note="Perhatian" tone="warn" icon="bi-exclamation" />
            </div>

            <div class="mb-3 flex items-center gap-3">
                <span class="text-xs text-stone-500">Filter kategori</span>
                <select id="filter-kategori" class="select-matcha">
                    <option value="">Semua kategori</option>
                    @foreach ($tags as $kategori)
                        <option value="{{ $kategori }}">{{ $kategori }}</option>
                    @endforeach
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="table-matcha">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th>Harga mulai</th>
                            <th>Varian</th>
                            <th>Total stok</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php
                                $threshold = $product->lowStockAlert?->min_threshold ?? 0;
                                $totalStock = $product->total_stock;
                                $stockStatus = $totalStock <= 0 ? 'Habis' : ($threshold > 0 && $product->variants->contains(fn ($variant) => $variant->stock < $threshold) ? 'Menipis' : 'Aman');
                            @endphp
                            <tr data-kategori="{{ $tagOf($product) }}">
                                <td>
                                    <div class="font-semibold text-matcha-900">{{ $product->name }}</div>
                                    <div class="max-w-xs truncate text-xs text-stone-500">{{ $product->description }}</div>
                                </td>
                                <td>{{ $tagOf($product) }}</td>
                                <td class="whitespace-nowrap">Rp {{ number_format((int) $product->base_price, 0, ',', '.') }}</td>
                                <td>
                                    <details>
                                        <summary class="cursor-pointer text-xs font-semibold text-matcha-700">{{ $product->variants_count }} varian</summary>
                                        <ul class="mt-1 space-y-1 rounded-lg bg-matcha-50/70 p-2 text-xs text-stone-600">
                                            @foreach ($product->variants as $variant)
                                                <li class="flex justify-between gap-2">
                                                    <span>{{ $variant->variant_name }}</span>
                                                    <span class="shrink-0">stok {{ $variant->stock }} · +Rp {{ number_format((int) $variant->additional_price, 0, ',', '.') }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                </td>
                                <td>{{ $totalStock }}</td>
                                <td>
                                    <div class="flex flex-col items-start gap-1">
                                        <x-status-badge :status="$product->is_active ? 'Aktif' : 'Nonaktif'" :label="$product->is_active ? 'Aktif' : 'Nonaktif'" />
                                        <x-status-badge :status="$stockStatus" :label="'Stok '.strtolower($stockStatus)" />
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('admin.products.edit', $product) }}" class="text-xs font-semibold text-matcha-700 hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline"
                                        data-confirm="Hapus produk ini? Tindakan ini tidak bisa dibatalkan.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ml-2 text-xs font-semibold text-red-600 hover:underline">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-stone-400">Belum ada produk. Tambahkan produk pertama.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
