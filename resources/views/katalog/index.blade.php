@php
    $levels = $sweetness ?? ['25%', '50%', '100%'];
    $pickChoice = 'border text-xs font-bold transition border-matcha-200 bg-white text-stone-600 hover:border-matcha-500 peer-checked:border-matcha-700 peer-checked:bg-matcha-700 peer-checked:text-white peer-disabled:cursor-not-allowed peer-disabled:opacity-50';
@endphp

<x-layouts.dashboard :nav="\App\Support\Navigation::konsumen()" role-label="Konsumen"
    title="Katalog Menu" subtitle="Pilih minuman favorit dan sesuaikan dengan selera Anda">
    <div class="w-full">
        @if ($products->isEmpty())
            <div class="card-soft p-10 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-matcha-100 text-2xl text-matcha-700">
                    <i class="bi bi-cup-straw"></i>
                </span>
                <h2 class="mt-3 text-base font-bold text-matcha-900">Belum ada menu</h2>
                <p class="mt-1 text-sm text-stone-500">Menu akan tampil di sini setelah admin menambahkannya.</p>
            </div>
        @else
            <div id="katalog" class="card-soft p-5 sm:p-6">
                <h2 class="text-base font-bold text-matcha-900">Semua Menu</h2>
                <p class="text-xs text-stone-500">Pilih produk untuk melihat detail dan varian.</p>

                <div class="mt-1 grid gap-6 pt-3 lg:grid-cols-12">
                    {{-- Daftar produk --}}
                    <div class="lg:col-span-8">
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($products as $product)
                                <x-product-card :product="$product" :selectable="true" />
                            @endforeach
                        </div>
                    </div>

                    {{-- Panel kustomisasi (satu form per produk, yang tampil hanya produk terpilih) --}}
                    <div class="lg:col-span-4">
                        @foreach ($products as $product)
                            @php
                                $product->loadMissing('variants');
                                $variants = $product->variants;
                                $defaultVariant = $variants->first(fn ($v) => $v->stock > 0) ?? $variants->first();
                                $allOut = $variants->isNotEmpty() && $variants->every(fn ($v) => $v->stock <= 0);
                            @endphp
                            <form method="POST" action="{{ route('cart.add') }}" hidden
                                data-product-panel="{{ $product->id }}" data-base="{{ (int) $product->base_price }}"
                                class="rounded-2xl border border-matcha-100 bg-matcha-50/60 p-4">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="qty" value="1">

                                <h3 class="text-sm font-bold text-matcha-900">Sesuaikan Minuman</h3>
                                <p class="mt-0.5 text-xs font-semibold text-matcha-700">{{ $product->name }}</p>
                                <p class="mt-0.5 text-xs text-stone-500">{{ $product->description ?: 'Matcha premium yang disiapkan segar untukmu.' }}</p>

                                @if ($variants->isNotEmpty())
                                    <div class="mt-4">
                                        <div class="mb-1.5 text-xs font-semibold text-stone-600">Ukuran / Varian</div>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($variants as $variant)
                                                <label class="cursor-pointer" title="{{ $variant->stock <= 0 ? 'Stok habis' : 'Stok tersisa '.$variant->stock }}">
                                                    <input type="radio" name="product_variant_id" value="{{ $variant->id }}"
                                                        data-add="{{ (int) $variant->additional_price }}" class="peer sr-only"
                                                        @checked($defaultVariant && $defaultVariant->id === $variant->id)
                                                        @disabled($variant->stock <= 0)>
                                                    <span class="{{ $pickChoice }} inline-flex h-8 min-w-10 items-center justify-center rounded-lg px-3">{{ $variant->variant_name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if ($allOut)
                                            <p class="mt-1.5 text-[11px] font-semibold text-red-600">Semua varian sedang habis.</p>
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-4">
                                    <div class="mb-1.5 text-xs font-semibold text-stone-600">Tingkat kemanisan</div>
                                    <div class="flex gap-2">
                                        @foreach ($levels as $level)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="sweet" value="{{ $level }}" class="peer sr-only" @checked($level === '50%')>
                                                <span class="{{ $pickChoice }} inline-flex rounded-lg px-3 py-1.5">{{ $level }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <label class="mb-1.5 block text-xs font-semibold text-stone-600" for="catatan-{{ $product->id }}">Catatan tambahan</label>
                                    <input id="catatan-{{ $product->id }}" name="note" type="text" maxlength="255" class="input-matcha !py-2 !text-xs" placeholder="Contoh: es sedikit">
                                </div>

                                <div class="mt-5 flex items-center justify-between border-t border-matcha-100 pt-4">
                                    <span class="text-xs text-stone-500">Total harga</span>
                                    <span data-total class="text-lg font-extrabold text-matcha-700">Rp {{ number_format((int) $product->base_price + (int) ($defaultVariant->additional_price ?? 0), 0, ',', '.') }}</span>
                                </div>

                                <button type="submit" class="btn-matcha mt-3 w-full" @disabled($allOut)>
                                    <i class="bi bi-plus-lg"></i> Tambah ke Keranjang
                                </button>

                                @if (session('success'))
                                    <a href="{{ route('cart.index') }}" class="mt-2 block w-full text-center text-xs font-semibold text-matcha-700 no-underline hover:underline">Ditambahkan ✓ — Lihat keranjang</a>
                                @endif
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.dashboard>
