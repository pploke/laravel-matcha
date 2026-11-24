@props(['light' => false])

@php
    $display = str_contains((string) $attributes->get('class'), 'hidden') ? '' : 'flex';
@endphp

<a href="{{ url('/') }}" {{ $attributes->merge(['class' => 'group '.$display.' items-center gap-2 no-underline']) }}>
    <span class="{{ $light ? 'text-white' : 'text-matcha-700' }} transition-transform duration-300 group-hover:animate-wiggle">
        <svg viewBox="0 0 24 24" class="h-6 w-6 origin-bottom-left animate-leaf-sway [--r:0deg] [animation-duration:6s]" fill="currentColor" aria-hidden="true">
            <path d="M20.5 3.5c-7.6.2-13 3.7-14.4 9.6-.3 1.2-.2 2.3.1 3.3L3 19.6l1.4 1.2 3.3-3.1c1 .4 2.1.5 3.3.3 5.9-1.1 9.4-6.6 9.5-14.5z" />
        </svg>
    </span>
    <span class="whitespace-nowrap text-lg font-extrabold leading-none">
        <span class="{{ $light ? 'text-white' : 'text-matcha-900' }}">Matcha</span>
        <span class="{{ $light ? 'text-matcha-200' : 'text-matcha-600' }} font-semibold italic">and</span>
        <span class="{{ $light ? 'text-white' : 'text-matcha-900' }}">Me</span>
    </span>
</a>
