@props(['label' => null, 'error' => null, 'icon' => null, 'type' => 'text'])

<label class="block">
    @if ($label)
        <span class="mb-1.5 block text-sm font-medium text-[color:var(--color-text-primary)]">{{ $label }}</span>
    @endif
    <div class="relative">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[color:var(--color-text-muted)]">{{ $icon }}</span>
        @endif
        <input
            type="{{ $type }}"
            {{ $attributes->merge([
                'class' => trim('w-full rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-4 py-3 text-sm text-[color:var(--color-text-primary)] placeholder:text-[color:var(--color-text-muted)] shadow-sm transition focus-ring '.($icon ? 'pl-10' : ''))
            ]) }}
        >
    </div>
    @if ($error)
        <p class="mt-1.5 text-sm text-[color:var(--color-danger)]">{{ $error }}</p>
    @endif
</label>
