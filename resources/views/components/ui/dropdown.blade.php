@props(['trigger' => null])

<div x-data="{ open: false }" class="relative">
    <button type="button" x-on:click="open = !open" class="focus-ring">
        {{ $trigger ?? 'Abrir' }}
    </button>
    <div x-cloak x-show="open" x-on:click.outside="open = false" class="absolute right-0 z-20 mt-2 w-56 rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-2 shadow-2xl">
        {{ $slot }}
    </div>
</div>
