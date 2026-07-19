@props(['for' => null, 'required' => false])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'block text-sm font-medium text-[color:var(--color-text-primary)]']) }}>
    {{ $slot }}
    @if ($required)
        <span class="text-[color:var(--color-danger)]" aria-hidden="true">*</span>
        <span class="sr-only"> (obligatorio)</span>
    @endif
</label>
