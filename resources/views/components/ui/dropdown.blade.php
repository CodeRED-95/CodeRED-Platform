@props(['trigger' => null])

<div x-data="{ open: false }" x-on:keydown.escape.stop="open = false; $refs.trigger.focus()" class="relative">
    <button x-ref="trigger" type="button" x-on:click="open = !open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu" class="focus-ring">
        {{ $trigger ?? 'Abrir' }}
    </button>
    <div x-cloak x-show="open" x-transition.opacity.duration.100ms x-on:click.outside="open = false" role="menu" class="absolute right-0 z-50 mt-2 w-56 rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-2 shadow-2xl">
        {{ $slot }}
    </div>
</div>
