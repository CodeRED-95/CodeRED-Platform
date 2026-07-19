@props(['label' => null, 'error' => null, 'wrapperClass' => ''])

<label class="block {{ $wrapperClass }}">
    @if ($label)
        <span class="mb-1.5 block text-sm font-medium text-[color:var(--color-text-primary)]">{{ $label }}</span>
    @endif
    <textarea {{ $attributes->merge(['class' => 'w-full rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-4 py-3 text-sm text-[color:var(--color-text-primary)] placeholder:text-[color:var(--color-text-muted)] shadow-sm transition focus-ring']) }}>{{ $slot }}</textarea>
    @if ($error)
        <p class="mt-1.5 text-sm text-[color:var(--color-danger)]">{{ $error }}</p>
    @endif
</label>
