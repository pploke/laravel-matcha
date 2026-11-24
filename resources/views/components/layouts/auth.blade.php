@props([
    'variant' => 'login',
    'title' => 'Login',
    'subtitle' => '',
])

@php
    $formAnim = 'space-y-4 [&>*]:animate-fade-up [&>*:nth-child(1)]:[animation-delay:150ms] [&>*:nth-child(2)]:[animation-delay:370ms] [&>*:nth-child(3)]:[animation-delay:370ms] [&>*:nth-child(n+4)]:[animation-delay:480ms]';
@endphp

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
    <div class="relative min-h-screen">
        {{-- Latar bergelombang --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden bg-gradient-to-br from-matcha-50 to-matcha-200">
            @if ($variant === 'login')
                <svg viewBox="0 0 800 300" preserveAspectRatio="none" class="absolute bottom-0 left-0 h-56 w-full animate-drift">
                    <path d="M0 140 C120 60 240 120 360 150 C480 180 560 90 800 130 L800 300 L0 300Z" fill="#84c030" opacity=".55" />
                </svg>
                <svg viewBox="0 0 800 300" preserveAspectRatio="none" class="absolute bottom-0 left-0 h-56 w-full animate-drift-slow">
                    <path d="M0 200 C140 120 260 190 380 210 C520 232 640 160 800 190 L800 300 L0 300Z" fill="#37700d" />
                </svg>
            @else
                <svg viewBox="0 0 400 600" preserveAspectRatio="none" class="absolute left-0 top-0 h-full w-1/3 min-w-[220px] animate-fade-in">
                    <path d="M0 0 H300 C330 120 220 200 250 330 C275 440 170 520 190 600 H0Z" fill="#285409" />
                    <path d="M0 0 H200 C230 90 140 150 160 250 C180 340 90 420 110 600 H0Z" fill="#37700d" opacity=".6" />
                </svg>
                <svg viewBox="0 0 500 400" preserveAspectRatio="none" class="absolute bottom-0 right-0 h-2/5 w-1/2 animate-drift">
                    <path d="M500 60 C400 70 330 150 250 230 C170 310 90 330 0 400 H500Z" fill="#84c030" opacity=".6" />
                    <path d="M500 150 C420 170 360 230 300 300 C250 355 150 380 90 400 H500Z" fill="#64a51b" opacity=".7" />
                </svg>
            @endif
            <x-decor preset="auth" />
        </div>

        @if ($variant === 'login')
            <div class="relative mx-auto grid min-h-screen max-w-[1600px] items-center gap-10 px-6 py-10 sm:px-10 lg:grid-cols-2 lg:px-16">
                {{-- Sisi kiri: sambutan --}}
                <div class="hidden self-stretch lg:flex lg:flex-col lg:py-6">
                    <div class="animate-slide-right"><x-logo /></div>
                    <div class="mt-16 max-w-sm animate-fade-up [animation-delay:150ms]">
                        <h1 class="text-3xl font-extrabold leading-tight text-matcha-900">Selamat Datang di Matcha and Me!</h1>
                        <p class="mt-3 text-sm text-stone-500">Nikmati kesegaran matcha terbaik dalam setiap tegukan.</p>
                    </div>
                    <div class="mt-auto h-64 w-44 animate-float pb-4">
                        <x-drink-image tone="latte" :rounded="false" alt="Matcha" class="!bg-transparent" />
                    </div>
                </div>

                {{-- Sisi kanan: kartu --}}
                <div class="mx-auto w-full max-w-md animate-pop-in rounded-3xl bg-white p-8 shadow-soft lg:ml-auto lg:mr-0">
                    <a href="{{ route('landing') }}" class="group mb-4 inline-flex items-center gap-1.5 rounded-full py-1 pr-3 text-sm font-semibold text-matcha-700 no-underline transition hover:text-matcha-900">
                        <i class="bi bi-arrow-left transition-transform duration-200 group-hover:-translate-x-1"></i>
                        Kembali
                    </a>
                    <div class="mb-6 text-center">
                        <div class="mb-4 inline-block lg:hidden"><x-logo /></div>
                        <h2 class="text-2xl font-extrabold text-matcha-900">{{ $title }}</h2>
                        @if ($subtitle)
                            <p class="mt-1 text-sm text-stone-500">{{ $subtitle }}</p>
                        @endif
                    </div>

                    {{ $slot }}
                </div>
            </div>
        @else
            <div class="relative z-10 block px-6 pt-6 sm:absolute sm:left-12 sm:top-8 sm:px-0 sm:pt-0">
                <x-logo :light="true" class="hidden sm:flex" />
                <x-logo class="sm:hidden" />
            </div>

            <div class="relative flex min-h-screen items-center justify-center px-6 py-10">
                <div class="w-full max-w-sm animate-pop-in rounded-3xl bg-white p-8 shadow-soft">
                    <div class="mb-6 text-center">
                        <h2 class="text-2xl font-extrabold text-matcha-900">{{ $title }}</h2>
                        @if ($subtitle)
                            <p class="mt-1 text-sm text-stone-500">{{ $subtitle }}</p>
                        @endif
                    </div>

                    {{ $slot }}
                </div>
            </div>
        @endif
    </div>
</body>
</html>
