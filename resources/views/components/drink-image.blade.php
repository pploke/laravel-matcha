@props(['src' => null, 'tone' => 'latte', 'alt' => '', 'rounded' => true])

@php
    $tones = [
        'latte' => ['liquid' => '#6fb326', 'top' => '#9fd24a', 'bg' => '#d6ea9f'],
        'choco' => ['liquid' => '#7a5232', 'top' => '#6fb326', 'bg' => '#ecd9b8'],
        'milk' => ['liquid' => '#8ccb3e', 'top' => '#e3f2bd', 'bg' => '#cfe8a0'],
        'berry' => ['liquid' => '#e5708c', 'top' => '#6fb326', 'bg' => '#f8d9e0'],
    ];
    $t = $tones[$tone] ?? $tones['latte'];

    // 1) pakai gambar dari database kalau filenya ada / berupa URL
    $resolved = null;
    if ($src) {
        $clean = ltrim((string) $src, '/');
        if (preg_match('#^(https?:)?//#', $src)) {
            // URL contoh bawaan seeder lama (example.com) tidak dianggap gambar sungguhan
            $host = (string) parse_url(str_starts_with($src, '//') ? 'https:'.$src : $src, PHP_URL_HOST);
            if (! preg_match('/(^|\.)example\.(com|org|net)$/i', $host)) {
                $resolved = $src;
            }
        } elseif (str_starts_with($src, 'data:')) {
            $resolved = $src;
        } elseif (file_exists(public_path($clean))) {
            $resolved = asset($clean);
        } elseif (file_exists(public_path('storage/'.$clean))) {
            $resolved = asset('storage/'.$clean);
        }
    }

    // 2) kalau belum ada, cari foto asli di public/images/menu yang namanya cocok dengan nama produk
    if (! $resolved && $alt) {
        $photos = collect(glob(public_path('images/menu/*.{jpg,jpeg,png,webp}'), GLOB_BRACE) ?: [])
            ->mapWithKeys(fn ($path) => [pathinfo($path, PATHINFO_FILENAME) => basename($path)]);
        $slug = \Illuminate\Support\Str::slug($alt);
        $match = $photos->keys()->filter(fn ($name) => str_contains($slug, $name))->sortByDesc(fn ($name) => strlen($name))->first();
        if ($match) {
            $resolved = asset('images/menu/'.$photos[$match]);
        }
    }
@endphp

@if ($resolved)
    <img src="{{ $resolved }}" alt="{{ $alt }}" loading="lazy"
        {{ $attributes->merge(['class' => 'h-full w-full object-cover '.($rounded ? 'rounded-xl' : '')]) }} />
@else
    <div {{ $attributes->merge(['class' => 'flex h-full w-full items-center justify-center '.($rounded ? 'rounded-xl' : '')]) }}
        style="background: {{ $t['bg'] }}" role="img" aria-label="{{ $alt }}">
        <svg viewBox="0 0 100 130" class="h-4/5 max-h-full">
            <rect x="46" y="2" width="5" height="40" rx="2.5" fill="#37700d" transform="rotate(8 48 22)" />
            <path d="M22 30h56l-6 92a6 6 0 0 1-6 5H34a6 6 0 0 1-6-5z" fill="#ffffff" opacity=".55" />
            <path d="M25 48h50l-4.4 72a5 5 0 0 1-5 4.6H34.4a5 5 0 0 1-5-4.6z" fill="{{ $t['liquid'] }}" />
            <path d="M25 48h50l-.8 12H25.8z" fill="{{ $t['top'] }}" />
            <rect x="34" y="70" width="13" height="13" rx="3" fill="#fff" opacity=".35" transform="rotate(-10 40 76)" />
            <rect x="52" y="86" width="12" height="12" rx="3" fill="#fff" opacity=".3" transform="rotate(12 58 92)" />
            <rect x="18" y="26" width="64" height="8" rx="4" fill="#285409" />
        </svg>
    </div>
@endif
