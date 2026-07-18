@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'disabled' => false,
    'loading' => false,
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
        @if ($loading)
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity=".2"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>
        @endif
        {{ $slot }}
    </button>
@endif
