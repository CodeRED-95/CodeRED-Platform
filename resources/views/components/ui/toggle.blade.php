@props([
    'id' => null,
    'label' => null,
    'description' => null,
    'disabled' => false,
])

@php
    $wireModel = collect(array_keys($attributes->getAttributes()))->first(fn (string $key): bool => str_starts_with($key, 'wire:model'));
    $fieldName = $attributes->get('name') ?: ($wireModel ? $attributes->get($wireModel) : null);
    $controlId = $id ?: ($fieldName ? 'toggle-'.str_replace(['.', '_'], '-', (string) $fieldName) : 'toggle-'.uniqid());
    $descriptionId = $description ? $controlId.'-description' : null;
@endphp

<div class="flex items-start gap-3">
    <span class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full bg-white/10 ring-1 ring-inset ring-white/10 transition focus-within:ring-2 focus-within:ring-[color:var(--color-brand)] focus-within:ring-offset-2 focus-within:ring-offset-[color:var(--color-background)] has-[:checked]:bg-[color:var(--color-brand-soft)]">
        <input
            id="{{ $controlId }}"
            type="checkbox"
            role="switch"
            @disabled($disabled)
            @if ($descriptionId) aria-describedby="{{ $descriptionId }}" @endif
            x-init="$el.setAttribute('aria-checked', $el.checked.toString())"
            x-on:change="$el.setAttribute('aria-checked', $el.checked.toString())"
            {{ $attributes->merge(['class' => 'peer sr-only']) }}
        >
        <span class="pointer-events-none absolute left-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5 peer-checked:bg-[color:var(--color-brand)] peer-disabled:opacity-50"></span>
    </span>
    <div class="min-w-0">
        <label for="{{ $controlId }}" class="block cursor-pointer text-sm font-medium text-[color:var(--color-text-primary)]">{{ $label ?? $slot }}</label>
        @if ($description)
            <p id="{{ $descriptionId }}" class="mt-1 text-xs leading-5 text-[color:var(--color-text-secondary)]">{{ $description }}</p>
        @endif
    </div>
</div>
