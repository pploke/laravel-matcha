@props(['label' => '', 'value' => '', 'note' => null, 'tone' => 'up', 'icon' => 'bi-circle-fill'])

@php
    $noteTones = [
        'up' => 'text-matcha-600',
        'warn' => 'text-amber-600',
        'muted' => 'text-stone-400',
    ];
@endphp

<div class="card-soft h-full p-4">
    <div class="flex items-center gap-2 text-xs text-stone-500">
        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-matcha-100 text-[9px] text-matcha-700">
            <i class="bi {{ $icon }}"></i>
        </span>
        {{ $label }}
    </div>
    <div class="mt-2 flex items-end justify-between gap-2">
        <div class="text-xl font-extrabold leading-tight text-matcha-900">{{ $value }}</div>
        @if ($note)
            <div class="pb-0.5 text-xs font-semibold {{ $noteTones[$tone] ?? $noteTones['up'] }}">{{ $note }}</div>
        @endif
    </div>
</div>
