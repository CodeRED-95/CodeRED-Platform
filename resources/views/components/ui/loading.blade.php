@props(['variant' => 'card', 'rows' => 3])

<x-ui.skeleton :variant="$variant" :rows="$rows" {{ $attributes }} />
