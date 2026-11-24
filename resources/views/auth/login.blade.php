<x-layouts.auth variant="login" title="Login" subtitle="Masuk ke akun Anda untuk melanjutkan">
    <form method="POST" action="{{ route('login.store') }}" id="auth-form"
        class="space-y-4 [&>*]:animate-fade-up [&>*:nth-child(1)]:[animation-delay:150ms] [&>*:nth-child(2)]:[animation-delay:370ms] [&>*:nth-child(3)]:[animation-delay:370ms] [&>*:nth-child(n+4)]:[animation-delay:480ms]">
        @csrf

        <div>
            <label class="label-matcha" for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                class="input-matcha" placeholder="Masukkan email Anda">
        </div>

        <div>
            <label class="label-matcha" for="password">Password</label>
            <input id="password" name="password" type="password" required
                class="input-matcha" placeholder="Masukkan password Anda">
        </div>

        <div class="flex items-center justify-between text-sm">
            <label class="flex cursor-pointer items-center gap-2 text-stone-600">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-4 w-4 accent-matcha-700">
                Ingat saya
            </label>
            {{-- Belum ada fitur reset password di backend: tampilan saja --}}
            <span class="cursor-default font-semibold text-matcha-700" title="Segera hadir">Lupa password?</span>
        </div>

        @if ($errors->any())
            <div class="animate-pop-in rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <button type="submit" class="btn-matcha w-full">Masuk</button>

        <div class="flex items-center gap-3 text-xs text-stone-400">
            <span class="h-px flex-1 bg-stone-200"></span>
            atau
            <span class="h-px flex-1 bg-stone-200"></span>
        </div>

        {{-- Belum ada login Google di backend: tampilan saja --}}
        <button type="button" class="btn-outline-matcha w-full" disabled title="Segera hadir">
            <i class="bi bi-google"></i> Masuk dengan Google
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-stone-500">
        Belum punya akun?
        <a href="{{ route('register') }}" class="font-semibold text-matcha-700 hover:underline">Daftar di sini</a>
    </p>
</x-layouts.auth>
