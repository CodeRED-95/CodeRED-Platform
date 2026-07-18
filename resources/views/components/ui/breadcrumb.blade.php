@props(['items' => []])

<nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2">
    @foreach ($items as $item)
        @if (! $loop->first)
            <span aria-hidden="true" class="text-[color:var(--color-text-muted)]">/</span>
        @endif
        @if (!empty($item['url']))
            <a href="{{ $item['url'] }}" class="hover:text-[color:var(--color-text-primary)]">{{ $item['label'] }}</a>
        @else
            <span class="text-[color:var(--color-text-secondary)]">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
