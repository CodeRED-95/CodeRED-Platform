@props(['title', 'subtitle' => null, 'actions' => null])

<div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div class="space-y-2">
        <div class="flex items-center gap-2 text-sm text-[color:var(--color-text-secondary)]">
            <x-ui.breadcrumb :items="$breadcrumb ?? []" />
        </div>
        <h1 class="font-display text-3xl font-semibold tracking-tight text-[color:var(--color-text-primary)] lg:text-4xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="max-w-3xl text-sm text-[color:var(--color-text-secondary)]">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($actions)
        <div class="flex flex-wrap gap-3">{{ $actions }}</div>
    @endif
</div>
