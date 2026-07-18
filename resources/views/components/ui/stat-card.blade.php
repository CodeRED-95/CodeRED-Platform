@props(['label', 'value', 'icon' => null, 'href' => null, 'tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'text-slate-200',
        'success' => 'text-[color:var(--color-success)]',
        'warning' => 'text-[color:var(--color-warning)]',
        'danger' => 'text-[color:var(--color-danger)]',
        'brand' => 'text-[color:var(--color-brand-light)]',
        'ivory' => 'text-[color:var(--color-accent-ivory)]',
        'info' => 'text-[color:var(--color-info)]',
    ];
@endphp

<article class="rounded-[var(--radius-card)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] p-5 transition hover:bg-[color:var(--color-surface-hover)]">
    @if ($href)
        <a href="{{ $href }}" class="block">
    @endif
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm text-[color:var(--color-text-secondary)]">{{ $label }}</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight {{ $tones[$tone] ?? $tones['neutral'] }}">{{ $value }}</p>
        </div>
        @if ($icon)
            <div class="rounded-2xl bg-white/5 p-3 text-[color:var(--color-brand-light)]">{{ $icon }}</div>
        @endif
    </div>
    @if ($href)
        </a>
    @endif
</article>
