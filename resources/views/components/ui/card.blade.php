@props([
    'padding' => 'p-6',
    'class' => '',
    'title' => null,
    'description' => null,
])

<section {{ $attributes->merge(['class' => trim('glass-panel rounded-[var(--radius-card)] '.$padding.' '.$class)]) }}>
    @if ($title)
        <x-ui.section-header :title="$title" :description="$description">
            @isset($actions)
                <x-slot:actions>{{ $actions }}</x-slot:actions>
            @endisset
        </x-ui.section-header>
        <div class="mt-[var(--space-section)]">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif
</section>
