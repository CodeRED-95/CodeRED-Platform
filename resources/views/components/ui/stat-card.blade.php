@props(['label', 'value', 'icon' => null, 'href' => null, 'tone' => 'neutral', 'description' => null])

@php
    $tones = [
        'neutral' => ['value' => 'text-slate-200', 'icon' => 'bg-slate-500/10 text-slate-300'],
        'success' => ['value' => 'text-[color:var(--color-success)]', 'icon' => 'bg-emerald-500/10 text-emerald-300'],
        'warning' => ['value' => 'text-[color:var(--color-warning)]', 'icon' => 'bg-amber-500/10 text-amber-200'],
        'danger' => ['value' => 'text-[color:var(--color-danger)]', 'icon' => 'bg-rose-500/10 text-rose-200'],
        'brand' => ['value' => 'text-[color:var(--color-brand-light)]', 'icon' => 'bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-light)]'],
        'ivory' => ['value' => 'text-[color:var(--color-accent-ivory)]', 'icon' => 'bg-white/5 text-[color:var(--color-accent-ivory)]'],
        'info' => ['value' => 'text-[color:var(--color-info)]', 'icon' => 'bg-sky-500/10 text-sky-200'],
    ];
    $selectedTone = $tones[$tone] ?? $tones['neutral'];
@endphp

<article {{ $attributes->class('group rounded-[var(--radius-card)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] p-5 transition duration-200 hover:-translate-y-0.5 hover:border-[color:var(--color-border)] hover:bg-[color:var(--color-surface-hover)]') }}>
    @if ($href)
        <a href="{{ $href }}" class="focus-ring block rounded-[var(--radius-control)]" wire:navigate>
    @endif
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm text-[color:var(--color-text-secondary)]">{{ $label }}</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight {{ $selectedTone['value'] }}">{{ number_format((int) $value) }}</p>
            @if ($description)
                <p class="mt-2 text-xs leading-5 text-[color:var(--color-text-muted)]">{{ $description }}</p>
            @endif
        </div>
        @if ($icon)
            <div class="rounded-2xl p-3 transition group-hover:scale-105 {{ $selectedTone['icon'] }}">{{ $icon }}</div>
        @endif
    </div>
    @if ($href)
        </a>
    @endif
</article>
