@props(['nav' => [], 'roleLabel' => 'Konsumen', 'title' => '', 'subtitle' => '', 'showCart' => false])

@php
    $initial = strtoupper(mb_substr(auth()->user()?->name ?? 'U', 0, 1));
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?: 'Dashboard' }} · Matcha and Me</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-matcha-50">
    <div class="min-h-screen lg:flex">
        {{-- Sidebar desktop --}}
        <aside class="sticky top-0 hidden h-screen w-64 shrink-0 border-r border-matcha-100 bg-matcha-100/40 lg:block">
            <x-sidebar :nav="$nav" :role-label="$roleLabel" />
        </aside>

        {{-- Sidebar mobile (drawer) --}}
        <div id="drawer" class="fixed inset-0 z-40 hidden lg:hidden">
            <div class="absolute inset-0 bg-black/40" data-drawer-close></div>
            <aside class="absolute left-0 top-0 h-full w-64 bg-matcha-50 shadow-xl">
                <x-sidebar :nav="$nav" :role-label="$roleLabel" />
            </aside>
        </div>

        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-matcha-100 bg-matcha-50/90 px-4 py-3 backdrop-blur sm:px-8">
                <button type="button" class="rounded-lg p-2 text-matcha-800 hover:bg-matcha-100 lg:hidden" data-drawer-open aria-label="Buka menu">
                    <i class="bi bi-list text-xl"></i>
                </button>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-bold leading-tight text-matcha-900">{{ $title }}</h1>
                    <p class="truncate text-xs text-stone-500">{{ $subtitle }}</p>
                </div>
                <div class="relative hidden sm:block">
                    <i class="bi bi-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-stone-400"></i>
                    <input class="w-56 rounded-xl border border-matcha-200 bg-white py-2 pl-8 pr-3 text-sm outline-none placeholder:text-stone-400 focus:border-matcha-500" placeholder="Cari sesuatu...">
                </div>
                @auth
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-matcha-200 text-sm font-bold text-matcha-800">{{ $initial }}</div>
                        <span class="hidden text-sm font-semibold text-matcha-900 sm:inline">
                            {{ auth()->user()->isAdmin() ? 'Admin' : explode(' ', auth()->user()->name)[0] }}
                        </span>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn-matcha !py-1.5 text-xs no-underline">
                        <i class="bi bi-box-arrow-in-right"></i> Masuk
                    </a>
                @endauth
            </header>

            <main class="px-4 py-6 sm:px-8">
                @if (session('success'))
                    <div class="mb-4 animate-pop-in rounded-xl bg-matcha-100 px-4 py-2.5 text-sm font-semibold text-matcha-800" role="status">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-4 animate-pop-in rounded-xl bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700" role="alert">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 animate-pop-in rounded-xl bg-red-50 px-4 py-2.5 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="page-enter">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</body>
</html>
