@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'disabled' => false,
    'loading' => false,
    'loadingTarget' => null,
    'loadingLabel' => 'Procesando…',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-[var(--radius-control)] font-medium transition duration-200 focus-ring disabled:cursor-not-allowed disabled:opacity-60';
    $sizes = [
        'sm' => 'px-3 py-2 text-sm',
        'md' => 'px-4 py-2.5 text-sm',
        'lg' => 'px-5 py-3 text-sm',
        'icon' => 'h-10 w-10 p-0',
    ];
    $variants = [
        'primary' => 'bg-[color:var(--color-brand)] text-white hover:bg-[color:var(--color-brand-hover)] active:bg-[color:var(--color-brand-active)]',
        'secondary' => 'bg-[color:var(--color-surface)] text-[color:var(--color-text-primary)] ring-1 ring-inset ring-[color:var(--color-border)] hover:bg-[color:var(--color-surface-hover)]',
        'danger' => 'bg-[color:var(--color-danger)] text-white hover:opacity-90',
        'ghost' => 'bg-transparent text-[color:var(--color-text-primary)] hover:bg-white/5',
        'outline' => 'bg-transparent text-[color:var(--color-text-primary)] ring-1 ring-inset ring-[color:var(--color-border)] hover:bg-white/5',
        'link' => 'bg-transparent px-0 text-[color:var(--color-brand-light)] hover:text-white',
    ];
    $classes = trim($base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->except('type')->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $attributes->get('type', $type) }}" @disabled($disabled || $loading) {{ $attributes->except('type')->merge(['class' => $classes]) }}>
        @if ($loadingTarget)
            <span wire:loading.remove wire:target="{{ $loadingTarget }}">{{ $slot }}</span>
            <span wire:loading wire:target="{{ $loadingTarget }}" class="items-center gap-2">
                <x-ui.spinner size="sm" :label="$loadingLabel" />
                {{ $loadingLabel }}
            </span>
        @else
            @if ($loading)
                <x-ui.spinner size="sm" :label="$loadingLabel" />
            @endif
            {{ $slot }}
        @endif
    </button>
@endif
