@props([
    'id',
    'title' => 'Confirmar acción',
    'message' => null,
    'confirmLabel' => 'Confirmar',
    'cancelLabel' => 'Cancelar',
    'confirmAction' => null,
    'tone' => 'danger',
    'confirmationText' => null,
    'icon' => null,
    'form' => null,
    'loadingLabel' => 'Procesando…',
])

{{--
    Diálogo de confirmación del Design System. Reemplaza window.confirm()
    en toda acción sensible (restaurar, eliminar, revocar...).

    Dos modos de confirmación, mutuamente excluyentes:

    1. Livewire (modo original, sin cambios de comportamiento):
       confirm-action="metodo(args)" invoca $wire.metodo(...) al confirmar.
       Con confirmation-text además, exige escribir un texto exacto antes
       de habilitar el botón (ver agencies/index.blade.php bulk-force-delete).

    2. Formulario tradicional (nuevo, para páginas sin Livewire como
       RUC Backup): form="id-del-form" referencia un <form method="POST">
       @csrf ya presente en la página. Al confirmar, Alpine llama a
       form.requestSubmit() — sigue siendo un POST nativo del navegador,
       nunca fetch/AJAX. El botón queda deshabilitado y muestra un spinner
       para evitar doble submit mientras el navegador procesa la petición.

    Tonos: neutral | brand (o "primary", alias retrocompatible) | info |
    success | warning | danger. Determinan tanto el color del ícono del
    header como la variante del botón de confirmación.
--}}
@php
    $toneMap = [
        'neutral' => ['classes' => 'bg-white/10 text-[color:var(--color-text-secondary)]', 'variant' => 'secondary', 'icon' => 'shield'],
        'brand' => ['classes' => 'bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-light)]', 'variant' => 'primary', 'icon' => 'shield'],
        'primary' => ['classes' => 'bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-light)]', 'variant' => 'primary', 'icon' => 'shield'],
        'info' => ['classes' => 'bg-sky-500/10 text-[color:var(--color-info)]', 'variant' => 'info', 'icon' => 'info'],
        'success' => ['classes' => 'bg-emerald-500/10 text-[color:var(--color-success)]', 'variant' => 'success', 'icon' => 'success'],
        'warning' => ['classes' => 'bg-amber-500/10 text-[color:var(--color-warning)]', 'variant' => 'warning', 'icon' => 'warning'],
        'danger' => ['classes' => 'bg-rose-500/10 text-[color:var(--color-danger)]', 'variant' => 'danger', 'icon' => 'warning'],
    ];
    $resolved = $toneMap[$tone] ?? $toneMap['danger'];
    $buttonVariant = $resolved['variant'];
    $iconName = $icon ?? $resolved['icon'];
@endphp

<div
    x-data="{
        open: false,
        submitting: false,
        confirmation: '',
        close() {
            if (this.submitting) return;
            this.open = false;
            this.confirmation = '';
            this.$nextTick(() => this.$refs.trigger.querySelector('button, a')?.focus({ preventScroll: true }));
        },
        destroy() {
            document.body.classList.remove('overflow-hidden');
        },
        confirmSubmit() {
            if (this.submitting) return;
            this.submitting = true;
            this.$nextTick(() => {
                const form = document.getElementById(@js($form));
                if (! form) { this.submitting = false; return; }
                form.requestSubmit ? form.requestSubmit() : form.submit();
            });
        },
        trapFocus(event) {
            const focusable = [...this.$refs.dialog.querySelectorAll('button, [href], input, textarea, [tabindex]:not([tabindex=\'-1\'])')];
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus({ preventScroll: true });
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus({ preventScroll: true });
            }
        },
    }"
    x-effect="document.body.classList.toggle('overflow-hidden', open)"
    x-on:keydown.escape.window="if (open && !submitting) close()"
>
    <span x-ref="trigger" x-on:click="open = true; submitting = false; confirmation = ''; $nextTick(() => $refs.cancel.focus({ preventScroll: true }))">
        {{ $trigger }}
    </span>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-transition.opacity.duration.150ms
            class="layer-modal fixed inset-0"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $id }}-title"
            aria-describedby="{{ $id }}-description"
            x-on:keydown.tab="trapFocus($event)"
        >
            <div class="absolute inset-0 bg-black/75 backdrop-blur-sm" x-on:click="!submitting && close()"></div>
            <div class="relative mx-auto flex min-h-full max-w-xl items-center px-4 py-8">
                <div x-ref="dialog" class="max-h-[85vh] w-full overflow-y-auto rounded-[var(--radius-modal)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-6 shadow-2xl">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $resolved['classes'] }}" aria-hidden="true">
                            <x-ui.icon :name="$iconName" class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 id="{{ $id }}-title" class="font-display text-xl font-semibold">{{ $title }}</h2>
                            <div @if($form) x-show="!submitting" @endif>
                                @if ($message)
                                    <p id="{{ $id }}-description" class="mt-2 text-sm text-[color:var(--color-text-secondary)]">{{ $message }}</p>
                                @endif
                                @if (! $slot->isEmpty())
                                    <div @unless($message) id="{{ $id }}-description" @endunless class="mt-4 text-sm text-[color:var(--color-text-secondary)]">
                                        {{ $slot }}
                                    </div>
                                @endif
                            </div>
                            @if ($form)
                                {{-- Estado "en curso": sustituye la descripción mientras el POST tradicional está en vuelo. Progreso indeterminado a propósito — no hay forma de conocer el % real de una operación síncrona. --}}
                                <div class="mt-2 space-y-3" x-show="submitting" x-cloak>
                                    <x-ui.progress indeterminate :label="$loadingLabel" :show-value="false" />
                                    <p class="text-xs text-[color:var(--color-text-secondary)]">No cierres ni recargues esta pestaña. La página se actualizará automáticamente al finalizar.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($confirmationText)
                        <div class="mt-6">
                            <label for="{{ $id }}-confirmation" class="mb-1.5 block text-sm font-medium text-[color:var(--color-text-primary)]">
                                Escribe <strong>{{ $confirmationText }}</strong> para confirmar
                            </label>
                            <input
                                x-ref="confirmation"
                                x-model="confirmation"
                                id="{{ $id }}-confirmation"
                                type="text"
                                autocomplete="off"
                                x-on:keydown.enter.prevent
                                class="focus-ring min-h-12 w-full rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background)] px-4 py-3 text-[color:var(--color-text-primary)]"
                                aria-describedby="{{ $id }}-description"
                            >
                        </div>
                    @endif

                    <div class="mt-6 flex justify-end gap-3">
                        @if ($form)
                            {{-- Modo formulario tradicional: sin Livewire, sin fetch. requestSubmit() sobre un <form> real que ya vive en la página. --}}
                            <x-ui.button x-ref="cancel" type="button" variant="secondary" x-on:click="close()" x-bind:disabled="submitting">
                                {{ $cancelLabel }}
                            </x-ui.button>
                            <x-ui.button type="button" :variant="$buttonVariant" x-on:click="confirmSubmit()" x-bind:disabled="submitting">
                                <span x-show="!submitting">{{ $confirmLabel }}</span>
                                <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
                                    <x-ui.spinner size="sm" :label="$loadingLabel" />
                                    {{ $loadingLabel }}
                                </span>
                            </x-ui.button>
                        @elseif ($confirmAction && $confirmationText)
                            <x-ui.button x-ref="cancel" type="button" variant="secondary" x-on:click="close()">{{ $cancelLabel }}</x-ui.button>
                            <x-ui.button
                                type="button"
                                :variant="$buttonVariant"
                                x-on:click="$wire.{{ $confirmAction }}(confirmation); close()"
                                x-bind:disabled="confirmation !== @js($confirmationText)"
                                loading-target="{{ $confirmAction }}"
                                loading-label="{{ $loadingLabel }}"
                                wire:loading.attr="disabled"
                                wire:target="{{ $confirmAction }}"
                            >
                                {{ $confirmLabel }}
                            </x-ui.button>
                        @elseif ($confirmAction)
                            <x-ui.button x-ref="cancel" type="button" variant="secondary" x-on:click="close()">{{ $cancelLabel }}</x-ui.button>
                            <x-ui.button
                                type="button"
                                :variant="$buttonVariant"
                                wire:click="{{ $confirmAction }}"
                                loading-target="{{ $confirmAction }}"
                                loading-label="{{ $loadingLabel }}"
                                wire:loading.attr="disabled"
                                wire:target="{{ $confirmAction }}"
                                x-on:click="close()"
                            >
                                {{ $confirmLabel }}
                            </x-ui.button>
                        @else
                            <x-ui.button x-ref="cancel" type="button" variant="secondary" x-on:click="close()">{{ $cancelLabel }}</x-ui.button>
                            <x-ui.button type="button" :variant="$buttonVariant" x-on:click="close()">
                                {{ $confirmLabel }}
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
