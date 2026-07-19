@props([
    'size' => 'md',
    'label' => 'Cargando',
])

@php
    $sizes = [
        'sm' => 'h-4 w-4',
        'md' => 'h-5 w-5',
        'lg' => 'h-8 w-8',
    ];
@endphp

<span role="status" {{ $attributes->class('inline-flex items-center justify-center') }}>
    <svg class="animate-spin {{ $sizes[$size] ?? $sizes['md'] }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity=".2"/>
        <path d="M22 12A10 10 0 0 0 12 2" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
    </svg>
    <span class="sr-only">{{ $label }}</span>
</span>
