@props([
    'open' => false,
    'title' => null,
    'closeLabel' => 'Cerrar',
])

<div x-data="{ open: @js($open), destroy() { document.body.classList.remove('overflow-hidden') }, trap(event) { const items = [...this.$refs.dialog.querySelectorAll('button,[href],input,textarea,[tabindex]:not([tabindex=\'-1\'])')]; if (!items.length) return; const first = items[0], last = items[items.length - 1]; if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); } } }" x-effect="document.body.classList.toggle('overflow-hidden', open)" x-on:keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="layer-modal fixed inset-0" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif x-on:keydown.tab="trap($event)">
            <div class="absolute inset-0 bg-black/70" x-on:click="open = false"></div>
            <div class="relative mx-auto flex min-h-full max-w-3xl items-center px-4 py-8">
                <div x-ref="dialog" class="max-h-[calc(100dvh-4rem)] w-full overflow-y-auto rounded-[var(--radius-modal)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-6 shadow-[var(--shadow-lg)]">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            @if ($title)
                                <h3 class="text-xl font-semibold">{{ $title }}</h3>
                            @endif
                        </div>
                        <x-ui.icon-button x-on:click="open = false" :label="$closeLabel">✕</x-ui.icon-button>
                    </div>
                    <div class="mt-4">{{ $slot }}</div>
                </div>
            </div>
        </div>
    </template>
</div>
