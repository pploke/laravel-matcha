<x-layouts.auth variant="register" title="Daftar" subtitle="Buat akun untuk mulai memesan">
    <form method="POST" action="{{ route('register.store') }}" id="auth-form"
        class="space-y-4 [&>*]:animate-fade-up [&>*:nth-child(1)]:[animation-delay:150ms] [&>*:nth-child(2)]:[animation-delay:370ms] [&>*:nth-child(3)]:[animation-delay:370ms] [&>*:nth-child(n+4)]:[animation-delay:480ms]">
        @csrf

        <div>
            <label class="label-matcha" for="name">Nama</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                class="input-matcha" placeholder="Masukkan nama lengkap">
        </div>

        <div>
            <label class="label-matcha" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required
                class="input-matcha" placeholder="Masukkan email aktif">
        </div>

        <div>
            <label class="label-matcha" for="password">Sandi</label>
            <div class="relative">
                <input id="password" name="password" type="password" required
                    class="input-matcha pr-10" placeholder="Buat kata sandi">
                <button type="button" data-toggle-password="password" aria-label="Tampilkan sandi"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 hover:text-matcha-700">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="animate-pop-in rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <button type="submit" class="btn-matcha w-full">Daftar</button>

        <div class="flex items-center gap-3 text-xs text-stone-400">
            <span class="h-px flex-1 bg-stone-200"></span>
            atau
            <span class="h-px flex-1 bg-stone-200"></span>
        </div>

        {{-- Belum ada login Google di backend: tampilan saja --}}
        <button type="button" disabled title="Segera hadir"
            class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-stone-200 bg-stone-50 px-4 py-2.5 text-sm font-semibold text-stone-700 opacity-70">
            <i class="bi bi-google"></i> Daftar dengan Google
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-stone-500">
        Sudah punya akun?
        <a href="{{ route('login') }}" class="font-semibold text-matcha-700 hover:underline">Masuk</a>
    </p>
</x-layouts.auth>
