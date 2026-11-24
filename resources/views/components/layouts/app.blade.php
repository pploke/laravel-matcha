@props(['title' => 'Matcha and Me'])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Matcha and Me</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-matcha-50">
    @php
        $navLink = 'text-sm font-medium text-stone-600 no-underline transition hover:text-matcha-700';
    @endphp

    <div class="min-h-screen bg-matcha-50">
        {{-- Navbar --}}
        <header class="relative z-20 mx-auto flex max-w-[1600px] animate-fade-in items-center gap-6 px-6 py-5 sm:px-10 lg:px-16">
            <x-logo />
            <nav class="ml-10 hidden items-center gap-6 md:flex">
                <a href="{{ route('landing') }}#beranda" data-scroll-to="beranda" class="{{ $navLink }} border-b-2 border-matcha-700 pb-0.5 !text-matcha-900">Beranda</a>
                <a href="{{ route('landing') }}#produk" data-scroll-to="produk" class="{{ $navLink }}">Katalog</a>
                <a href="{{ route('landing') }}#tentang" data-scroll-to="tentang" class="{{ $navLink }}">Tentang Kami</a>
                <a href="{{ route('landing') }}#kontak" data-scroll-to="kontak" class="{{ $navLink }}">Kontak</a>
                <a href="{{ route('orders.history') }}" class="{{ $navLink }}">Riwayat Pesanan</a>
            </nav>
            <div class="ml-auto flex items-center gap-3">
                <div class="relative hidden lg:block">
                    <i class="bi bi-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-stone-400"></i>
                    <input class="w-52 rounded-full border border-matcha-200 bg-white py-1.5 pl-8 pr-3 text-sm outline-none placeholder:text-stone-400 focus:border-matcha-500" placeholder="Cari matcha favoritmu...">
                </div>
                @auth
                    <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('beranda') }}" class="btn-matcha !rounded-full !py-1.5 no-underline">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-full border border-matcha-700 px-4 py-1.5 text-sm font-semibold text-matcha-700 no-underline transition hover:bg-white">Masuk</a>
                    <a href="{{ route('register') }}" class="btn-matcha !rounded-full !py-1.5 no-underline">Daftar</a>
                @endauth
            </div>
        </header>

        @if (session('success'))
            <div class="mx-auto max-w-[1600px] px-6 sm:px-10 lg:px-16">
                <div class="animate-pop-in rounded-xl bg-matcha-100 px-4 py-2.5 text-sm font-semibold text-matcha-800" role="status">{{ session('success') }}</div>
            </div>
        @endif
        @if (session('error'))
            <div class="mx-auto max-w-[1600px] px-6 sm:px-10 lg:px-16">
                <div class="animate-pop-in rounded-xl bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700" role="alert">{{ session('error') }}</div>
            </div>
        @endif

        {{ $slot }}

        {{-- Footer ringkas (tujuan link Tentang Kami & Kontak) --}}
        <footer class="relative overflow-hidden bg-matcha-900 text-matcha-100">
            <x-decor preset="footer" />
            <div class="relative mx-auto grid max-w-[1600px] gap-8 px-6 py-10 sm:grid-cols-2 sm:px-10 lg:px-16">
                <div id="tentang">
                    <x-logo :light="true" />
                    <p class="mt-3 max-w-sm text-sm text-matcha-200">
                        Matcha and Me menyajikan matcha premium grade Uji yang disiapkan segar untuk setiap momen.
                    </p>
                </div>
                <div id="kontak" class="text-sm">
                    <div class="mb-2 font-semibold text-white">Kontak</div>
                    <p class="text-matcha-200">Gerai Telang · Surabaya</p>
                    <p class="text-matcha-200">halo@matchaandme.id</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
