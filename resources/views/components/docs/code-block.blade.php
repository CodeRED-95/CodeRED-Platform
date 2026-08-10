@props([
    'code' => '',
    'language' => 'text',
])

{{--
    Bloque de código con buen contraste, scroll horizontal y botón de copiar.
    El fondo usa el color de fondo base del Design System para máximo contraste
    con el texto claro. El código se pasa como texto plano (sin resaltar) para
    no depender de librerías externas.
--}}
<div class="group relative overflow-hidden rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background)]">
    <div class="flex items-center justify-between border-b border-[color:var(--color-border)] bg-white/[0.02] px-3 py-1.5">
        <span class="font-mono text-[11px] uppercase tracking-wide text-[color:var(--color-text-muted)]">{{ $language }}</span>
        <x-docs.copy-button :text="$code" label="Copiar" class="!border-0 !bg-transparent !px-1.5 !py-1" />
    </div>
    <pre class="overflow-x-auto px-4 py-3 text-xs leading-relaxed text-slate-200"><code class="font-mono">{{ $code }}</code></pre>
</div>
