@props(['product', 'selected' => false, 'selectable' => false])

@php
    $product = $product->loadMissing('variants');
    $price = (int) $product->base_price;
    $href = route('katalog.index', ['produk' => $product->id]);
    // baca kolom asli (bukan accessor model) supaya kategori & warna sesuai data seeder
    $tag = $product->getAttributes()['tag'] ?? $product->tag;
    $tone = $product->getAttributes()['tone'] ?? $product->tone;
@endphp

<div @if ($selectable) data-select-product="{{ $product->id }}" @endif
    class="card-soft group overflow-hidden p-2.5 transition-all duration-700 ease-[cubic-bezier(.16,1,.3,1)] hover:-translate-y-1.5 hover:shadow-lift {{ $selectable ? 'cursor-pointer' : '' }} {{ $selected ? 'ring-2 ring-matcha-600' : '' }}">
    <div class="aspect-square overflow-hidden rounded-xl transition-transform duration-[1200ms] ease-[cubic-bezier(.16,1,.3,1)] group-hover:scale-[1.05]">
        <x-drink-image :src="$product->image" :tone="$tone" :alt="$product->name" />
    </div>
    <div class="flex items-end justify-between gap-2 px-1 pb-1 pt-3">
        <div class="min-w-0">
            <div class="line-clamp-2 min-h-[2.5rem] text-sm font-semibold text-matcha-900" title="{{ $product->name }}">{{ $product->name }}</div>
            <div class="mt-0.5 text-sm font-bold text-matcha-700">Rp {{ number_format($price, 0, ',', '.') }}</div>
            <div class="text-[11px] text-stone-400">{{ $tag }}</div>
        </div>
        @if ($selectable)
            <button type="button" aria-label="Pilih {{ $product->name }}"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-matcha-700 text-white transition-all duration-300 ease-out hover:rotate-90 hover:scale-110 hover:bg-matcha-800 active:scale-95">
                <i class="bi bi-plus-lg text-sm"></i>
            </button>
        @else
            <a href="{{ $href }}" aria-label="Tambah {{ $product->name }}"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-matcha-700 text-white no-underline transition-all duration-300 ease-out hover:rotate-90 hover:scale-110 hover:bg-matcha-800 active:scale-95">
                <i class="bi bi-plus-lg text-sm"></i>
            </a>
        @endif
    </div>
</div>
