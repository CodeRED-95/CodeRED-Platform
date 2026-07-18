@props([
    'id' => null,
    'name' => null,
    'label' => 'Estado',
    'value' => null,
    'options' => [],
    'required' => false,
    'disabled' => false,
    'error' => null,
])

<x-ui.dropdown-select
    :id="$id"
    :name="$name"
    :label="$label"
    :value="$value"
    :options="$options"
    :required="$required"
    :disabled="$disabled"
    :error="$error"
    icon-set="agency-status"
    {{ $attributes }}
/>
