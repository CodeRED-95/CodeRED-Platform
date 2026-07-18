@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-white/5 text-[color:var(--color-text-secondary)] ring-white/10',
        'success' => 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20',
        'warning' => 'bg-amber-500/10 text-amber-200 ring-amber-500/20',
        'danger' => 'bg-rose-500/10 text-rose-200 ring-rose-500/20',
        'brand' => 'bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-light)] ring-[color:var(--color-brand-soft)]',
        'info' => 'bg-sky-500/10 text-sky-200 ring-sky-500/20',
        'ivory' => 'bg-[color:var(--color-accent-ivory)]/10 text-[color:var(--color-accent-ivory)] ring-[color:var(--color-accent-ivory)]/20',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 '.($tones[$tone] ?? $tones['neutral'])]) }}>
    {{ $slot }}
</span>
