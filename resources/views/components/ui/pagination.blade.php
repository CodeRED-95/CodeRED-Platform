@props(['paginator', 'scrollTo' => false])

@if ($paginator->hasPages())
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-[color:var(--color-text-secondary)]">{{ __('pagination.showing', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</p>
        {{ $paginator->onEachSide(1)->links('vendor.pagination.codered', ['scrollTo' => $scrollTo]) }}
    </div>
@endif
