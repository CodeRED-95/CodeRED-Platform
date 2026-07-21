@props([
    'padding' => 'p-6',
    'class' => '',
    'title' => null,
    'description' => null,
    'variant' => 'default',
])

@php
    $variants = [
        'default' => 'glass-panel',
        'elevated' => 'border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] shadow-[var(--shadow-md)]',
        'interactive' => 'border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] transition hover:-translate-y-0.5 hover:border-[color:var(--color-border)] hover:bg-[color:var(--color-surface-hover)] hover:shadow-[var(--shadow-md)]',
        'metric' => 'border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] shadow-[var(--shadow-sm)]',
        'danger' => 'border border-rose-500/25 bg-rose-500/[0.07]',
        'success' => 'border border-emerald-500/25 bg-emerald-500/[0.07]',
        'warning' => 'border border-amber-500/25 bg-amber-500/[0.07]',
        'info' => 'border border-sky-500/25 bg-sky-500/[0.07]',
    ];
@endphp

<section {{ $attributes->merge(['class' => trim(($variants[$variant] ?? $variants['default']).' rounded-[var(--radius-card)] '.$padding.' '.$class)]) }}>
    @if ($title)
        <x-ui.section-header :title="$title" :description="$description">
            @isset($actions)
                <x-slot:actions>{{ $actions }}</x-slot:actions>
            @endisset
        </x-ui.section-header>
        <div class="mt-[var(--space-section)]">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif
    @isset($footer)<footer class="mt-[var(--space-section)] border-t border-[color:var(--color-border-subtle)] pt-4">{{ $footer }}</footer>@endisset
</section>
