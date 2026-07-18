@props(['title' => 'Sin resultados', 'description' => null, 'icon' => '•'])

<div class="rounded-[var(--radius-card)] border border-dashed border-[color:var(--color-border)] bg-[color:var(--color-surface)] p-8 text-center">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white/5 text-[color:var(--color-brand-light)]">
        {{ $icon }}
    </div>
    <h3 class="mt-4 text-lg font-semibold">{{ $title }}</h3>
    @if ($description)
        <p class="mt-2 text-sm text-[color:var(--color-text-secondary)]">{{ $description }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
