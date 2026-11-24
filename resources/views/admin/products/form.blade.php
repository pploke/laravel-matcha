@php
    $isEdit = $product->exists;

    $variantRows = old('variants');
    if ($variantRows === null) {
        $variantRows = $isEdit
            ? $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'variant_name' => $variant->variant_name,
                'additional_price' => (int) $variant->additional_price,
                'stock' => $variant->stock,
            ])->all()
            : [];
    }
    $variantRows = array_values($variantRows ?: []);
    if (count($variantRows) === 0) {
        $variantRows = [['id' => '', 'variant_name' => '', 'additional_price' => 0, 'stock' => 0]];
    }
@endphp

<x-layouts.dashboard :nav="\App\Support\Navigation::admin()" role-label="Admin"
    :title="$isEdit ? 'Edit Produk' : 'Tambah Produk'"
    subtitle="Lengkapi informasi produk, varian, dan stok">
    <div class="mx-auto w-full max-w-4xl">
        <a href="{{ route('admin.products.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-matcha-700 no-underline transition hover:text-matcha-900">
            <i class="bi bi-arrow-left"></i> Kembali ke daftar produk
        </a>

        <form method="POST" action="{{ $isEdit ? route('admin.products.update', $product) : route('admin.products.store') }}" class="card-soft p-5 sm:p-6">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <h2 class="text-base font-bold text-matcha-900">Informasi Produk</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label-matcha" for="name">Nama produk <span class="text-red-500">*</span></label>
                    <input id="name" name="name" required value="{{ old('name', $product->name) }}"
                        class="input-matcha" placeholder="Contoh: Matcha Latte Original">
                </div>

                <div class="sm:col-span-2">
                    <label class="label-matcha" for="description">Deskripsi</label>
                    <textarea id="description" name="description" rows="3" class="input-matcha"
                        placeholder="Deskripsi singkat produk">{{ old('description', $product->description) }}</textarea>
                </div>

                <div>
                    <label class="label-matcha" for="base_price">Harga dasar (Rp) <span class="text-red-500">*</span></label>
                    <input id="base_price" name="base_price" type="number" min="0" step="500" required
                        value="{{ old('base_price', (int) $product->base_price) }}" class="input-matcha" placeholder="25000">
                </div>

                <div>
                    <label class="label-matcha" for="cost_price">Harga modal (Rp)</label>
                    <input id="cost_price" name="cost_price" type="number" min="0" step="500"
                        value="{{ old('cost_price', (int) $product->cost_price) }}" class="input-matcha" placeholder="12000">
                </div>

                <div class="sm:col-span-2">
                    <label class="label-matcha" for="image_url">URL gambar (opsional)</label>
                    <input id="image_url" name="image_url" type="url" value="{{ old('image_url', $product->image_url) }}"
                        class="input-matcha" placeholder="https://...">
                    <p class="mt-1 text-xs text-stone-500">Kosongkan untuk memakai ilustrasi gelas matcha bawaan.</p>
                </div>

                <div>
                    <label class="label-matcha" for="min_threshold">Batas minimum stok</label>
                    <input id="min_threshold" name="min_threshold" type="number" min="0"
                        value="{{ old('min_threshold', $minThreshold) }}" class="input-matcha">
                </div>

                <div class="flex items-end">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-matcha-900">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                            @checked(old('is_active', $isEdit ? $product->is_active : true))
                            class="h-4 w-4 rounded accent-matcha-700">
                        Produk aktif dan tampil di katalog
                    </label>
                </div>
            </div>

            <div class="mt-6 border-t border-matcha-100 pt-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-bold text-matcha-900">Varian &amp; Stok</h3>
                        <p class="text-xs text-stone-500">Atur nama varian, tambahan harga, dan jumlah stok.</p>
                    </div>
                    <button type="button" id="add-variant" class="btn-outline-matcha !py-1.5 text-xs">
                        <i class="bi bi-plus-lg"></i> Tambah Varian
                    </button>
                </div>

                <div id="variant-list" class="mt-3 space-y-3">
                    @foreach ($variantRows as $index => $variant)
                        <div class="variant-row grid gap-3 rounded-xl border border-matcha-100 bg-matcha-50/40 p-3 sm:grid-cols-12">
                            <div class="sm:col-span-5">
                                <label class="label-matcha !text-xs">Nama varian</label>
                                <input name="variants[{{ $index }}][variant_name]" value="{{ $variant['variant_name'] ?? '' }}"
                                    class="input-matcha" placeholder="Contoh: Cup Besar (Less Sugar)">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="label-matcha !text-xs">Harga tambahan (Rp)</label>
                                <input name="variants[{{ $index }}][additional_price]" type="number" min="0" step="500"
                                    value="{{ $variant['additional_price'] ?? 0 }}" class="input-matcha">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label-matcha !text-xs">Stok</label>
                                <input name="variants[{{ $index }}][stock]" type="number" min="0"
                                    value="{{ $variant['stock'] ?? 0 }}" class="input-matcha">
                            </div>
                            <div class="flex items-end sm:col-span-2">
                                @if (! empty($variant['id']))
                                    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] }}">
                                    <span class="w-full rounded-lg bg-white px-2 py-2 text-center text-[11px] text-stone-400">Varian tersimpan</span>
                                @else
                                    <button type="button" data-remove-variant class="btn-sm-matcha w-full text-red-600 hover:bg-red-50">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-end gap-2 border-t border-matcha-100 pt-5">
                <a href="{{ route('admin.products.index') }}" class="btn-outline-matcha no-underline">Batal</a>
                <button type="submit" class="btn-matcha">
                    <i class="bi bi-check-lg"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Produk' }}
                </button>
            </div>
        </form>
    </div>

    <template id="variant-template">
        <div class="variant-row grid gap-3 rounded-xl border border-matcha-100 bg-matcha-50/40 p-3 sm:grid-cols-12">
            <div class="sm:col-span-5">
                <label class="label-matcha !text-xs">Nama varian</label>
                <input name="variants[__INDEX__][variant_name]" class="input-matcha" placeholder="Contoh: Cup Besar (Less Sugar)">
            </div>
            <div class="sm:col-span-3">
                <label class="label-matcha !text-xs">Harga tambahan (Rp)</label>
                <input name="variants[__INDEX__][additional_price]" type="number" min="0" step="500" value="0" class="input-matcha">
            </div>
            <div class="sm:col-span-2">
                <label class="label-matcha !text-xs">Stok</label>
                <input name="variants[__INDEX__][stock]" type="number" min="0" value="0" class="input-matcha">
            </div>
            <div class="flex items-end sm:col-span-2">
                <button type="button" data-remove-variant class="btn-sm-matcha w-full text-red-600 hover:bg-red-50">
                    <i class="bi bi-trash"></i> Hapus
                </button>
            </div>
        </div>
    </template>

    <script>
        (function () {
            const list = document.getElementById('variant-list');
            const template = document.getElementById('variant-template');
            let index = {{ count($variantRows) }};

            document.getElementById('add-variant')?.addEventListener('click', function () {
                const markup = template.innerHTML.replaceAll('__INDEX__', String(index++));
                list.insertAdjacentHTML('beforeend', markup);
            });

            list?.addEventListener('click', function (event) {
                const button = event.target.closest('[data-remove-variant]');
                if (!button) return;
                button.closest('.variant-row')?.remove();
            });
        })();
    </script>
</x-layouts.dashboard>
