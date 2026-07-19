@props(['paginator', 'scrollTo' => false])

@if ($paginator->hasPages())
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-[color:var(--color-text-secondary)]">
            Mostrando <span class="font-medium text-[color:var(--color-text-primary)]">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
            de <span class="font-medium text-[color:var(--color-text-primary)]">{{ $paginator->total() }}</span>
        </p>
        {{ $paginator->onEachSide(1)->links('vendor.pagination.codered', ['scrollTo' => $scrollTo]) }}
    </div>
@endif
