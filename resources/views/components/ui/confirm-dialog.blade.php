@props([
    'id',
    'title' => 'Confirmar acción',
    'message' => 'Esta acción requiere confirmación.',
    'confirmLabel' => 'Confirmar',
    'cancelLabel' => 'Cancelar',
    'confirmAction' => null,
    'tone' => 'danger',
])

<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="if (open) { open = false; $nextTick(() => $refs.trigger.querySelector('button, a')?.focus()) }"
>
    <span x-ref="trigger" x-on:click="open = true; $nextTick(() => $refs.cancel.focus())">
        {{ $trigger }}
    </span>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity.duration.150ms
        class="fixed inset-0 z-50"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $id }}-title"
        aria-describedby="{{ $id }}-description"
    >
        <div class="absolute inset-0 bg-black/75 backdrop-blur-sm" x-on:click="open = false"></div>
        <div class="relative mx-auto flex min-h-full max-w-lg items-center px-4 py-8">
            <div class="w-full rounded-[var(--radius-modal)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-6 shadow-2xl">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-500/10 text-[color:var(--color-danger)]" aria-hidden="true">!</div>
                    <div>
                        <h2 id="{{ $id }}-title" class="font-display text-xl font-semibold">{{ $title }}</h2>
                        <p id="{{ $id }}-description" class="mt-2 text-sm text-[color:var(--color-text-secondary)]">{{ $message }}</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-ui.button x-ref="cancel" type="button" variant="secondary" x-on:click="open = false">{{ $cancelLabel }}</x-ui.button>
                    @if ($confirmAction)
                        <x-ui.button
                            type="button"
                            :variant="$tone === 'danger' ? 'danger' : 'primary'"
                            wire:click="{{ $confirmAction }}"
                            x-on:click="open = false"
                        >
                            {{ $confirmLabel }}
                        </x-ui.button>
                    @else
                        <x-ui.button
                            type="button"
                            :variant="$tone === 'danger' ? 'danger' : 'primary'"
                            x-on:click="open = false"
                        >
                            {{ $confirmLabel }}
                        </x-ui.button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
