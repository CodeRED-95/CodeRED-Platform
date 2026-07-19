@props([
    'label' => 'Buscar',
    'placeholder' => 'Buscar…',
    'error' => null,
])

<x-ui.input
    type="search"
    :label="$label"
    :placeholder="$placeholder"
    :error="$error"
    {{ $attributes }}
>
    <x-slot:prefix>
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="8.75" cy="8.75" r="4.75" stroke="currentColor" stroke-width="1.75"/>
            <path d="m12.25 12.25 3.25 3.25" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
        </svg>
    </x-slot:prefix>
</x-ui.input>
