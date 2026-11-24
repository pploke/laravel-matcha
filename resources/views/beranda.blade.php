<x-layouts.dashboard :nav="\App\Support\Navigation::konsumen()" role-label="Konsumen"
    title="Beranda" subtitle="Nikmati kesegaran matcha alami">
    <div class="w-full">
        {{-- Banner --}}
        @php
            $featured = $products->first(fn ($p) => str_contains(strtolower($p->name), 'authentic')) ?? $products->first();
            $menuCount = $products->count();
            $minPrice = $menuCount ? (int) $products->min('base_price') : 0;
        @endphp
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-matcha-100 to-matcha-200 px-6 py-8 sm:px-10 sm:py-10">
            <div class="pointer-events-none absolute -right-10 -top-10 h-48 w-48 rounded-full bg-matcha-300/40"></div>

            <div class="relative z-10 grid items-center gap-8 sm:grid-cols-[1fr_auto]">
                <div class="max-w-lg">
                    <span class="text-[10px] font-bold tracking-[0.2em] text-matcha-600">MATCHA PREMIUM</span>
                    <h2 class="mt-2 text-3xl font-extrabold leading-tight text-matcha-900">
                        Nikmati Keaslian
                        <br>
                        Matcha Alami
                    </h2>
                    <p class="mt-2 text-sm text-stone-600">Premium grade Uji matcha untuk momen berharga.</p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ([['bi-leaf', 'Premium Grade Uji'], ['bi-cup-hot', 'Dibuat segar setiap hari'], ['bi-droplet-half', 'Less sugar, more matcha']] as [$icon, $text])
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/70 px-3 py-1 text-[11px] font-semibold text-matcha-800">
                                <i class="bi {{ $icon }} text-matcha-600"></i>{{ $text }}
                            </span>
                        @endforeach
                    </div>

                    <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-3">
                        <a href="{{ route('katalog.index') }}" class="btn-matcha !py-2 no-underline">Lihat Menu Kami</a>
                        @if ($menuCount)
                            <dl class="flex gap-5 text-xs">
                                <div>
                                    <dt class="text-stone-500">Pilihan menu</dt>
                                    <dd class="text-base font-extrabold text-matcha-900">{{ $menuCount }} menu</dd>
                                </div>
                                <div>
                                    <dt class="text-stone-500">Mulai dari</dt>
                                    <dd class="text-base font-extrabold text-matcha-900">Rp {{ number_format($minPrice, 0, ',', '.') }}</dd>
                                </div>
                            </dl>
                        @endif
                    </div>
                </div>

                {{-- Foto menu unggulan dalam bingkai + keterangan --}}
                <div class="relative mx-auto h-56 w-56 sm:mx-0 sm:h-64 sm:w-64">
                    <div class="h-full w-full overflow-hidden rounded-3xl bg-white shadow-lift ring-4 ring-white/80">
                        @if ($featured)
                            <x-drink-image :src="$featured->image" :tone="$featured->getAttributes()['tone'] ?? $featured->tone" :alt="$featured->name" :rounded="false" />
                        @else
                            <img src="{{ asset('images/menu/authentic-matcha-latte.jpg') }}" alt="Matcha" class="h-full w-full object-cover">
                        @endif
                    </div>

                    <span class="absolute -left-3 top-4 inline-flex items-center gap-1 rounded-full bg-amber-500 px-3 py-1 text-[11px] font-bold text-white shadow-soft">
                        <i class="bi bi-star-fill"></i> Menu Favorit
                    </span>

                    @if ($featured)
                        <div class="absolute -bottom-4 left-1/2 w-[calc(100%-1rem)] -translate-x-1/2 rounded-2xl bg-white px-3.5 py-2.5 shadow-lift">
                            <div class="line-clamp-1 text-xs font-bold text-matcha-900">{{ $featured->name }}</div>
                            <div class="mt-0.5 flex items-center justify-between gap-2">
                                <span class="line-clamp-1 text-[11px] text-stone-500">{{ \Illuminate\Support\Str::limit($featured->description, 34) }}</span>
                                <span class="shrink-0 text-sm font-extrabold text-matcha-700">Rp {{ number_format((int) $featured->base_price, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- Paling banyak dicari --}}
        <section class="mt-8">
            <h3 class="text-base font-bold text-matcha-900">Paling Banyak Dicari Bulan Ini 👑</h3>

            @if ($products->isEmpty())
                <p class="mt-4 text-sm text-stone-500">Belum ada produk aktif di server.</p>
            @else
                <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-layouts.dashboard>
