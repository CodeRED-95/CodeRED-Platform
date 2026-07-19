@props([
    'messages' => [],
    'duration' => 5000,
])

<div
    x-data="{
        toasts: [],
        nextId: 1,
        init() {
            @js(array_values($messages)).forEach((toast) => this.add(toast));
        },
        add(detail) {
            const payload = Array.isArray(detail) ? detail[0] : detail;
            if (!payload?.message) return;

            const toast = {
                id: this.nextId++,
                message: payload.message,
                tone: payload.tone ?? payload.type ?? 'info',
            };

            this.toasts.push(toast);
            window.setTimeout(() => this.remove(toast.id), @js($duration));
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
        toneClass(tone) {
            return {
                success: 'border-emerald-500/30 bg-emerald-950/95 text-emerald-100',
                danger: 'border-rose-500/30 bg-rose-950/95 text-rose-100',
                error: 'border-rose-500/30 bg-rose-950/95 text-rose-100',
                warning: 'border-amber-500/30 bg-amber-950/95 text-amber-100',
                info: 'border-sky-500/30 bg-sky-950/95 text-sky-100',
            }[tone] ?? 'border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] text-white';
        },
    }"
    x-on:toast.window="add($event.detail)"
    class="pointer-events-none fixed right-4 top-4 z-[80] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3"
    aria-live="polite"
    aria-atomic="false"
>
    <template x-for="toast in toasts" x-bind:key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-4 opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0 opacity-100"
            x-transition:leave-end="translate-x-4 opacity-0"
            role="status"
            class="pointer-events-auto flex items-start gap-3 rounded-[var(--radius-card)] border p-4 shadow-2xl backdrop-blur"
            x-bind:class="toneClass(toast.tone)"
        >
            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-current text-xs" aria-hidden="true" x-text="toast.tone === 'success' ? '✓' : '!' "></span>
            <p class="min-w-0 flex-1 text-sm font-medium" x-text="toast.message"></p>
            <button type="button" class="rounded p-1 opacity-70 transition hover:bg-white/10 hover:opacity-100 focus-ring" x-on:click="remove(toast.id)" aria-label="Cerrar notificación">✕</button>
        </div>
    </template>
</div>
