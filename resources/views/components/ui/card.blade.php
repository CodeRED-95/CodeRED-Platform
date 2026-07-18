@props(['padding' => 'p-6', 'class' => ''])

<section {{ $attributes->merge(['class' => trim('glass-panel rounded-[var(--radius-card)] '.$padding.' '.$class)]) }}>
    {{ $slot }}
</section>
