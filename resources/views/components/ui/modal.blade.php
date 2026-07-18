@props([
    'open' => false,
    'title' => null,
    'closeLabel' => 'Cerrar',
])

<div x-data="{ open: @js($open) }" x-show="open" x-cloak class="fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/70" x-on:click="open = false"></div>
    <div class="relative mx-auto flex min-h-full max-w-3xl items-center px-4 py-8">
        <div class="w-full rounded-[var(--radius-modal)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    @if ($title)
                        <h3 class="text-xl font-semibold">{{ $title }}</h3>
                    @endif
                </div>
                <button type="button" class="rounded-full p-2 text-[color:var(--color-text-secondary)] hover:bg-white/5 focus-ring" x-on:click="open = false" aria-label="{{ $closeLabel }}">✕</button>
            </div>
            <div class="mt-4">{{ $slot }}</div>
        </div>
    </div>
</div>
