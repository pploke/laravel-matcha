<x-layouts.dashboard :nav="\App\Support\Navigation::konsumen()" role-label="Konsumen"
    title="Detail Menu" subtitle="Sesuaikan varian, tingkat gula, dan catatan pesananmu">
    <div class="mx-auto w-full max-w-5xl">
        <a href="{{ route('katalog.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-matcha-700 no-underline transition hover:text-matcha-900">
            <i class="bi bi-arrow-left"></i> Kembali ke katalog
        </a>

        <div class="grid gap-5 lg:grid-cols-5">
            {{-- Gambar & deskripsi --}}
            <div class="card-soft overflow-hidden lg:col-span-2">
                <div class="aspect-[4/3]">
                    <x-drink-image :src="$product->image" :tone="$product->getAttributes()['tone'] ?? $product->tone" :alt="$product->name" />
                </div>
                <div class="p-5">
                    <span class="inline-block rounded-full bg-matcha-100 px-2.5 py-0.5 text-[11px] font-semibold text-matcha-700">
                        {{ $product->getAttributes()['tag'] ?? $product->tag }}
                    </span>
                    <h1 class="mt-2 text-xl font-extrabold text-matcha-900">{{ $product->name }}</h1>
                    <p class="mt-2 text-sm text-stone-500">{{ $product->description ?: 'Matcha premium yang disiapkan segar untukmu.' }}</p>
                    <div class="mt-4 text-sm text-stone-500">
                        Mulai dari
                        <span class="text-lg font-extrabold text-matcha-700">Rp {{ number_format((int) $product->base_price, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            {{-- Form pesanan --}}
            <form method="POST" action="{{ route('cart.add') }}" class="card-soft p-5 sm:p-6 lg:col-span-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if ($product->variants->isNotEmpty())
                    <div>
                        <span class="label-matcha">Pilih varian <span class="text-red-500">*</span></span>
                        <div class="mt-1.5 grid gap-2.5">
                            @foreach ($product->variants as $index => $variant)
                                @php
                                    $price = (int) $product->base_price + (int) $variant->additional_price;
                                    $outOfStock = $variant->stock <= 0;
                                @endphp
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-matcha-200 bg-white p-3 transition-all duration-300 has-[:checked]:border-matcha-700 has-[:checked]:bg-matcha-50 has-[:checked]:ring-1 has-[:checked]:ring-matcha-700 {{ $outOfStock ? 'cursor-not-allowed opacity-60' : 'hover:border-matcha-500' }}">
                                    <input type="radio" name="product_variant_id" value="{{ $variant->id }}"
                                        @checked(old('product_variant_id', $product->variants->first()->id) == $variant->id)
                                        @disabled($outOfStock) required class="h-4 w-4 accent-matcha-700">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-matcha-900">{{ $variant->variant_name }}</span>
                                        <span class="block text-xs {{ $outOfStock ? 'text-red-600' : 'text-stone-500' }}">
                                            {{ $outOfStock ? 'Stok habis' : 'Stok tersisa '.$variant->stock }}
                                        </span>
                                    </span>
                                    <span class="shrink-0 text-sm font-bold text-matcha-700">Rp {{ number_format($price, 0, ',', '.') }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="rounded-xl bg-matcha-50 px-3 py-2 text-sm text-matcha-800">
                        <i class="bi bi-info-circle mr-1"></i> Produk ini tidak memiliki varian.
                    </div>
                @endif

                <div class="mt-5">
                    <span class="label-matcha">Tingkat gula</span>
                    <div class="mt-1.5 flex flex-wrap gap-2">
                        @foreach ($sweetness as $level)
                            <label class="cursor-pointer">
                                <input type="radio" name="sweet" value="{{ $level }}" @checked(old('sweet', '50%') === $level) class="peer sr-only">
                                <span class="inline-flex items-center rounded-full border border-matcha-200 bg-white px-4 py-1.5 text-sm font-semibold text-stone-600 transition peer-checked:border-matcha-700 peer-checked:bg-matcha-700 peer-checked:text-white hover:border-matcha-500">
                                    {{ $level }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5">
                    <label class="label-matcha" for="note">Catatan (opsional)</label>
                    <input id="note" name="note" type="text" value="{{ old('note') }}" maxlength="255"
                        class="input-matcha" placeholder="Contoh: tanpa es, less sugar">
                </div>

                <div class="mt-5">
                    <label class="label-matcha" for="qty">Jumlah</label>
                    <div class="flex items-center gap-3">
                        <input id="qty" name="qty" type="number" min="1" max="99" value="{{ old('qty', 1) }}"
                            class="input-matcha !w-24" inputmode="numeric">
                        <span class="text-xs text-stone-500">Maksimal 99 per pesanan.</span>
                    </div>
                </div>

                <button type="submit" class="btn-matcha mt-6 w-full"
                    @disabled($product->variants->isNotEmpty() && $product->variants->every(fn ($variant) => $variant->stock <= 0))>
                    <i class="bi bi-bag-plus"></i> Tambahkan ke Keranjang
                </button>
            </form>
        </div>
    </div>
</x-layouts.dashboard>
