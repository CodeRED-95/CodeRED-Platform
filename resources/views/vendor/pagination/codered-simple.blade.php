@php
    $scrollAction = is_string($scrollTo ?? null)
        ? "document.querySelector('".$scrollTo."')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
        : '';

    // CursorPaginator no sabe en qué página va ni cuántas hay: por diseño no
    // ejecuta COUNT(*). Solo puede ofrecer "anterior" y "siguiente", y la
    // navegación se hace moviendo el cursor, no un número de página.
    $previousCursor = $paginator->previousCursor()?->encode();
    $nextCursor = $paginator->nextCursor()?->encode();
    $cursorName = $paginator->getCursorName();

    $enabled = 'focus-ring inline-flex min-h-10 items-center border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-3 py-2 text-sm font-medium text-[color:var(--color-text-primary)] transition-colors hover:bg-[color:var(--color-surface-hover)] disabled:cursor-not-allowed disabled:opacity-50';
    $disabled = 'inline-flex min-h-10 cursor-not-allowed items-center border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-3 py-2 text-sm text-[color:var(--color-text-disabled)] opacity-50';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('pagination.navigation') }}" class="flex items-center gap-1 justify-end">
        @if ($previousCursor)
            <button type="button"
                    wire:click="$set('{{ $cursorName }}', '{{ $previousCursor }}')"
                    @if ($scrollAction) x-on:click="{{ $scrollAction }}" @endif
                    wire:loading.attr="disabled"
                    class="{{ $enabled }} rounded-l-[var(--radius-control)]"
                    aria-label="{{ __('pagination.previous_label') }}">{{ __('pagination.previous') }}</button>
        @else
            <span aria-disabled="true" aria-label="{{ __('pagination.previous_label') }}" class="{{ $disabled }} rounded-l-[var(--radius-control)]">{{ __('pagination.previous') }}</span>
        @endif

        @if ($nextCursor)
            <button type="button"
                    wire:click="$set('{{ $cursorName }}', '{{ $nextCursor }}')"
                    @if ($scrollAction) x-on:click="{{ $scrollAction }}" @endif
                    wire:loading.attr="disabled"
                    class="{{ $enabled }} rounded-r-[var(--radius-control)]"
                    aria-label="{{ __('pagination.next_label') }}">{{ __('pagination.next') }}</button>
        @else
            <span aria-disabled="true" aria-label="{{ __('pagination.next_label') }}" class="{{ $disabled }} rounded-r-[var(--radius-control)]">{{ __('pagination.next') }}</span>
        @endif
    </nav>
@endif
