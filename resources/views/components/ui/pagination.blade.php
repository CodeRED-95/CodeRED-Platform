@if ($paginator->hasPages())
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm text-[color:var(--color-text-secondary)]">
            Mostrando {{ $paginator->firstItem() }} - {{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </div>
        <div>{{ $paginator->links() }}</div>
    </div>
@endif
