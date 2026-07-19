@props(['label' => null, 'error' => null, 'description' => null, 'wrapperClass' => '', 'required' => false])

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
    <textarea
        id="{{ $controlId }}"
        @if ($required) required @endif
        aria-invalid="{{ $error ? 'true' : 'false' }}"
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['id', 'aria-describedby'])->merge(['class' => trim('min-h-28 w-full rounded-[var(--radius-control)] border bg-[color:var(--color-surface)] px-4 py-3 text-sm text-[color:var(--color-text-primary)] placeholder:text-[color:var(--color-text-disabled)] shadow-[var(--shadow-control)] transition focus-ring disabled:cursor-not-allowed disabled:opacity-60 '.($error ? 'border-[color:var(--color-danger)]' : 'border-[color:var(--color-border)]'))]) }}
    >{{ $slot }}</textarea>
    @if ($description)
        <p id="{{ $descriptionId }}" class="mt-1.5 text-xs text-[color:var(--color-text-secondary)]">{{ $description }}</p>
    @endif
    <x-ui.form-error :id="$errorId" :message="$error" />
</div>
