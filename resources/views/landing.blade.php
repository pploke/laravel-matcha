<x-layouts.app title="Beranda">
    {{-- Hero --}}
    <section id="beranda" class="relative overflow-hidden bg-gradient-to-br from-matcha-100 via-matcha-50 to-matcha-200">
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-3/4 bg-gradient-to-t from-matcha-300/60 to-transparent"></div>
        <svg viewBox="0 0 1200 200" preserveAspectRatio="none" class="pointer-events-none absolute bottom-0 left-0 h-40 w-full animate-drift">
            <path d="M0 120 C200 40 360 150 560 110 C760 70 900 20 1200 90 L1200 200 L0 200Z" fill="#84c030" opacity=".55" />
        </svg>
        <x-decor preset="hero" />

        <div class="relative mx-auto grid max-w-[1600px] items-center gap-8 px-6 pb-16 pt-6 sm:px-10 md:grid-cols-2 lg:px-16">
            <div class="animate-fade-up">
                <h1 class="text-4xl font-extrabold leading-tight text-matcha-900 sm:text-5xl">
                    Nikmati Keaslian
                    <br>
                    Matcha Alami
                </h1>
                <p class="mt-4 max-w-sm text-sm text-stone-600">
                    Premium grade Uji matcha yang disiapkan dengan cinta untuk momen berharga mu.
                </p>
                <a href="{{ route('katalog.index') }}" class="btn-matcha group mt-6 !rounded-full !px-5 no-underline">
                    Lihat Menu Kami
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white/25">
                        <i class="bi bi-arrow-right text-xs transition-transform duration-200 group-hover:translate-x-0.5"></i>
                    </span>
                </a>
            </div>

            <div class="relative ml-auto h-72 w-full max-w-lg animate-pop-in [animation-delay:200ms] sm:h-96">
                <div class="absolute inset-x-4 bottom-0 top-8 animate-ring rounded-[2.5rem] border-2 border-matcha-500/60"></div>
                <div class="absolute inset-x-4 bottom-0 top-8 animate-ring rounded-[2.5rem] border-2 border-matcha-500/60 [animation-delay:2.2s]"></div>
                <div class="absolute inset-x-4 bottom-0 top-8 rounded-[2.5rem] bg-gradient-to-br from-matcha-300 to-matcha-400/80 shadow-lift"></div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <img src="{{ asset('images/menu/authentic-matcha-latte.jpg') }}" alt="Signature Authentic Matcha Latte — Matcha and Me"
                        class="aspect-square h-full animate-float rounded-[2.5rem] object-cover shadow-lift">
                </div>
            </div>
        </div>
    </section>

    {{-- Pita teks berjalan --}}
    <div class="overflow-hidden bg-matcha-800 py-3 text-matcha-100">
        <div class="flex w-max animate-marquee items-center">
            @foreach ([0, 1] as $copy)
                <div class="flex shrink-0 items-center" @if ($copy === 1) aria-hidden="true" @endif>
                    @foreach (['Premium Grade Uji', 'Dibuat Segar Setiap Hari', 'Less Sugar, More Matcha', 'Disiapkan dengan Cinta', 'Matcha and Me'] as $text)
                        <span class="flex items-center text-sm font-semibold uppercase tracking-[0.18em]">
                            <span class="mx-8 text-matcha-300">✦</span>{{ $text }}
                        </span>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    {{-- Preview produk --}}
    <section id="produk" class="relative overflow-hidden bg-gradient-to-b from-matcha-100 to-matcha-200 pb-16 pt-12">
        <x-decor preset="section" />
        <div class="relative mx-auto max-w-[1600px] px-6 sm:px-10 lg:px-16">
            <div class="flex items-end justify-between">
                <div class="reveal mx-auto text-center">
                    <h2 class="text-2xl font-extrabold text-matcha-900">Preview Produk &amp; Menu Utama</h2>
                </div>
                <div class="hidden gap-2 sm:flex">
                    <button type="button" data-slider="-1" aria-label="Sebelumnya" class="flex h-8 w-8 items-center justify-center rounded-full border border-matcha-300 bg-white text-matcha-700 hover:bg-matcha-50">
                        <i class="bi bi-chevron-left text-xs"></i>
                    </button>
                    <button type="button" data-slider="1" aria-label="Berikutnya" class="flex h-8 w-8 items-center justify-center rounded-full border border-matcha-300 bg-white text-matcha-700 hover:bg-matcha-50">
                        <i class="bi bi-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>

            <div id="produk-slider" class="mt-8 flex gap-5 overflow-x-auto pb-2 [scrollbar-width:none]">
                @forelse ($products as $product)
                    <div class="reveal w-64 shrink-0 sm:w-[calc(25%-15px)] sm:min-w-[220px]" style="--reveal-delay: {{ min($loop->index, 4) * 120 }}ms">
                        <x-product-card :product="$product" />
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Belum ada produk aktif.</p>
                @endforelse
            </div>
        </div>
    </section>
</x-layouts.app>
