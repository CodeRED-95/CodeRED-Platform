@props([
    'label' => null,
    'error' => null,
    'description' => null,
    'icon' => null,
    'type' => 'text',
    'wrapperClass' => '',
    'required' => false,
])

@php
    $attributeValues = $attributes->getAttributes();
    $wireModel = collect(array_keys($attributeValues))->first(fn (string $key): bool => str_starts_with($key, 'wire:model'));
    $fieldName = $attributes->get('name') ?: ($wireModel ? $attributes->get($wireModel) : null);
    $controlId = $attributes->get('id') ?: ($fieldName ? 'field-'.str_replace(['.', '_'], '-', (string) $fieldName) : 'field-'.uniqid());
    $errorId = $controlId.'-error';
    $descriptionId = $controlId.'-description';
    $describedBy = collect([$attributes->get('aria-describedby'), $description ? $descriptionId : null, $error ? $errorId : null])->filter()->join(' ');
@endphp

<div class="block {{ $wrapperClass }}">
    @if ($label)
        <x-ui.form-label :for="$controlId" :required="$required" class="mb-1.5">{{ $label }}</x-ui.form-label>
    @endif
    <div class="relative">
        @if ($icon || isset($prefix))
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[color:var(--color-text-muted)]" aria-hidden="true">{{ $icon }}</span>
            @isset($prefix)
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-[color:var(--color-text-muted)]">{{ $prefix }}</span>
            @endisset
        @endif
        <input
            id="{{ $controlId }}"
            type="{{ $type }}"
            @if ($required) required @endif
            aria-invalid="{{ $error ? 'true' : 'false' }}"
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except(['id', 'aria-describedby'])->merge([
                'class' => trim('min-h-[var(--control-height)] w-full rounded-[var(--radius-control)] border bg-[color:var(--color-surface)] px-4 py-3 text-sm text-[color:var(--color-text-primary)] placeholder:text-[color:var(--color-text-disabled)] shadow-[var(--shadow-control)] transition focus-ring disabled:cursor-not-allowed disabled:opacity-60 '.(($icon || isset($prefix)) ? 'pl-10 ' : '').(isset($suffix) ? 'pr-12 ' : '').($error ? 'border-[color:var(--color-danger)]' : 'border-[color:var(--color-border)]')),
            ]) }}
        >
        @isset($suffix)
            <span class="absolute inset-y-0 right-0 flex items-center pr-2">{{ $suffix }}</span>
        @endisset
    </div>
    @if ($description)
        <p id="{{ $descriptionId }}" class="mt-1.5 text-xs text-[color:var(--color-text-secondary)]">{{ $description }}</p>
    @endif
    <x-ui.form-error :id="$errorId" :message="$error" />
</div>
