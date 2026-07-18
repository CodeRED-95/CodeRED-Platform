@props(['label' => null, 'error' => null])

<label class="block">
    @if ($label)
        <span class="mb-1.5 block text-sm font-medium text-[color:var(--color-text-primary)]">{{ $label }}</span>
    @endif
    <select {{ $attributes->merge(['class' => 'w-full rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-4 py-3 text-sm text-[color:var(--color-text-primary)] shadow-sm transition focus-ring']) }}>
        {{ $slot }}
    </select>
    @if ($error)
        <p class="mt-1.5 text-sm text-[color:var(--color-danger)]">{{ $error }}</p>
    @endif
</label>
