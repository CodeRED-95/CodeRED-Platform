@props(['title', 'description' => null, 'actions' => null])

<div class="flex items-end justify-between gap-4">
    <div>
        <h2 class="font-display text-xl font-semibold">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">{{ $description }}</p>
        @endif
    </div>
    @if ($actions)
        <div>{{ $actions }}</div>
    @endif
</div>
