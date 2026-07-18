@props(['text'])

<span title="{{ $text }}" {{ $attributes->merge(['class' => 'cursor-help underline decoration-dotted decoration-[color:var(--color-text-muted)] underline-offset-4']) }}>
    {{ $slot }}
</span>
