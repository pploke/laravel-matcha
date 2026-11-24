@props(['title' => '', 'desc' => null])

<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h2 class="text-base font-bold text-matcha-900">{{ $title }}</h2>
        @if ($desc)
            <p class="text-xs text-stone-500">{{ $desc }}</p>
        @endif
    </div>
    @isset($action)
        {{ $action }}
    @endisset
</div>
