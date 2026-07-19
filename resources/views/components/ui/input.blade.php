@props([
    'label' => null,
    'error' => null,
    'icon' => null,
    'type' => 'text',
    'wrapperClass' => '',
])

<label class="block {{ $wrapperClass }}">
    @if ($label)
        <span class="mb-1.5 block text-sm font-medium text-[color:var(--color-text-primary)]">{{ $label }}</span>
    @endif
    <div class="relative">
        @if ($icon || isset($prefix))
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[color:var(--color-text-muted)]">{{ $icon }}</span>
            @isset($prefix)
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[color:var(--color-text-muted)]">{{ $prefix }}</span>
            @endisset
        @endif
        <input
            type="{{ $type }}"
            aria-invalid="{{ $error ? 'true' : 'false' }}"
            {{ $attributes->merge([
                'class' => trim('w-full rounded-[var(--radius-control)] border bg-[color:var(--color-surface)] px-4 py-3 text-sm text-[color:var(--color-text-primary)] placeholder:text-[color:var(--color-text-muted)] shadow-sm transition focus-ring '.(($icon || isset($prefix)) ? 'pl-10 ' : '').(isset($suffix) ? 'pr-12 ' : '').($error ? 'border-[color:var(--color-danger)]' : 'border-[color:var(--color-border)]'))
            ]) }}
        >
        @isset($suffix)
            <span class="absolute inset-y-0 right-0 flex items-center pr-2">{{ $suffix }}</span>
        @endisset
    </div>
    @if ($error)
        <p class="mt-1.5 text-sm text-[color:var(--color-danger)]">{{ $error }}</p>
    @endif
</label>
