@props(['nav' => [], 'roleLabel' => 'Konsumen'])

@php
    $user = auth()->user();
    $initial = strtoupper(mb_substr($user?->name ?? 'U', 0, 1));
    $isActive = fn ($item) => request()->routeIs($item['active'] ?? '__none__');
@endphp

<div class="flex h-full flex-col">
    <div class="px-5 pb-4 pt-5"><x-logo /></div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
        @foreach ($nav as $item)
            @if (! empty($item['children']))
                @php
                    $groupActive = collect($item['children'])->contains(fn ($c) => $isActive($c));
                    $key = \Illuminate\Support\Str::slug($item['label']);
                @endphp
                <div data-group="{{ $key }}">
                    <button type="button" data-group-toggle="{{ $key }}" aria-expanded="{{ $groupActive ? 'true' : 'false' }}"
                        class="flex w-full items-center gap-3 rounded-xl border-0 px-3 py-2.5 text-left text-sm font-medium transition {{ $groupActive ? 'bg-matcha-700 text-white shadow-sm' : 'bg-transparent text-stone-600 hover:bg-matcha-100' }}">
                        <i class="bi {{ $item['icon'] }}"></i>
                        <span class="flex-1">{{ $item['label'] }}</span>
                        <i data-chevron class="bi bi-chevron-down text-xs transition-transform duration-300 {{ $groupActive ? 'rotate-180' : '' }}"></i>
                    </button>
                    <div data-panel class="grid transition-all duration-300 ease-in-out {{ $groupActive ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0' }}">
                        <div class="overflow-hidden">
                            <div class="mt-0.5 space-y-0.5 pb-1">
                                @foreach ($item['children'] as $child)
                                    <a href="{{ $child['url'] }}" data-drawer-close
                                        class="block rounded-lg py-1.5 pl-11 pr-3 text-[13px] no-underline transition {{ $isActive($child) ? 'font-semibold text-matcha-700' : 'text-stone-500 hover:text-matcha-700' }}">{{ $child['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div>
                    <a href="{{ $item['url'] }}" data-drawer-close
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium no-underline transition {{ $isActive($item) ? 'bg-matcha-700 text-white shadow-sm' : 'text-stone-600 hover:bg-matcha-100' }}">
                        <i class="bi {{ $item['icon'] }}"></i>
                        {{ $item['label'] }}
                    </a>
                </div>
            @endif
        @endforeach
    </nav>

    @auth
        <div class="m-3 flex items-center gap-3 rounded-xl bg-matcha-100/70 p-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-matcha-700 text-sm font-bold text-white">{{ $initial }}</div>
            <div class="min-w-0 flex-1 leading-tight">
                <div class="truncate text-sm font-semibold text-matcha-900">{{ $user->name }}</div>
                <div class="truncate text-xs text-stone-500">{{ $roleLabel }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" title="Keluar" class="rounded-lg p-1.5 text-stone-500 transition hover:bg-white hover:text-matcha-700">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </form>
        </div>
    @else
        <div class="m-3 rounded-xl bg-matcha-100/70 p-3">
            <div class="text-sm font-semibold text-matcha-900">Belum masuk</div>
            <p class="mt-0.5 text-xs text-stone-500">Masuk agar riwayat pesanan tersimpan di akunmu.</p>
            <div class="mt-2 flex gap-2">
                <a href="{{ route('login') }}" class="btn-matcha flex-1 !py-1.5 text-xs no-underline">Masuk</a>
                <a href="{{ route('register') }}" class="btn-outline-matcha flex-1 !py-1.5 text-xs no-underline">Daftar</a>
            </div>
        </div>
    @endauth
</div>
