@props([
    'variant' => 'card',
    'rows' => 3,
])

<div role="status" aria-label="Cargando contenido" {{ $attributes->class('animate-pulse') }}>
    @if ($variant === 'table')
        <div class="overflow-hidden rounded-[var(--radius-card)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)]">
            <div class="grid grid-cols-4 gap-4 border-b border-[color:var(--color-border-subtle)] p-4">
                @foreach (range(1, 4) as $column)
                    <div class="h-3 rounded bg-white/10"></div>
                @endforeach
            </div>
            @foreach (range(1, max(1, $rows)) as $row)
                <div class="grid grid-cols-4 gap-4 border-b border-[color:var(--color-border-subtle)] p-4 last:border-b-0">
                    @foreach (range(1, 4) as $column)
                        <div class="h-4 rounded bg-white/5"></div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @elseif ($variant === 'text')
        <div class="space-y-3">
            @foreach (range(1, max(1, $rows)) as $row)
                <div class="h-4 rounded bg-white/10 {{ $loop->last ? 'w-2/3' : 'w-full' }}"></div>
            @endforeach
        </div>
    @else
        <div class="rounded-[var(--radius-card)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] p-4">
            <div class="h-4 w-1/3 rounded bg-white/10"></div>
            <div class="mt-4 h-4 w-2/3 rounded bg-white/10"></div>
        </div>
    @endif
    <span class="sr-only">Cargando contenido</span>
</div>
