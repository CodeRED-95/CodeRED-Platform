@props([
    'text' => '',
    'label' => 'Copiar',
])

{{--
    Botón de copiar al portapapeles, autónomo (Alpine). Muestra "Copiado" un
    instante tras copiar. Reutiliza el estilo de control del Design System.
--}}
<button
    type="button"
    {{ $attributes->merge(['class' => 'focus-ring inline-flex shrink-0 items-center gap-1.5 rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-2.5 py-1.5 text-xs font-medium text-[color:var(--color-text-secondary)] transition hover:text-white']) }}
    x-data="{
        copied: false,
        copy() {
            const value = @js($text);
            const done = () => { this.copied = true; setTimeout(() => this.copied = false, 1500); };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(done).catch(() => this.fallback(value, done));
            } else {
                this.fallback(value, done);
            }
        },
        fallback(value, done) {
            const area = document.createElement('textarea');
            area.value = value; area.style.position = 'fixed'; area.style.opacity = '0';
            document.body.appendChild(area); area.focus(); area.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(area);
        },
    }"
    x-on:click="copy()"
    x-bind:aria-label="copied ? 'Copiado' : @js($label)"
>
    <span aria-hidden="true" x-text="copied ? '✓' : '⧉'"></span>
    <span x-text="copied ? 'Copiado' : @js($label)"></span>
</button>
