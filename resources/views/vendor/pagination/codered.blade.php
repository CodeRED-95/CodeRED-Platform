@php
    $scrollAction = is_string($scrollTo ?? null)
        ? "document.querySelector('".$scrollTo."')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
        : '';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('pagination.navigation') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
        <div class="flex items-center justify-between gap-3 sm:hidden">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="{{ __('pagination.previous_label') }}" class="inline-flex min-h-11 cursor-not-allowed items-center rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-4 py-2 text-sm font-medium text-[color:var(--color-text-disabled)] opacity-50">
                    {{ __('pagination.previous') }}
                </span>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" @if ($scrollAction) x-on:click="{{ $scrollAction }}" @endif wire:loading.attr="disabled" class="focus-ring inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-4 py-2 text-sm font-medium text-[color:var(--color-text-primary)] transition-colors hover:bg-[color:var(--color-surface-hover)] disabled:cursor-not-allowed disabled:opacity-50" aria-label="{{ __('pagination.previous_label') }}">
                    {{ __('pagination.previous') }}
                </button>
            @endif

            <span class="text-sm text-[color:var(--color-text-secondary)]" aria-current="page">
                {{ __('pagination.page', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}
            </span>

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" @if ($scrollAction) x-on:click="{{ $scrollAction }}" @endif wire:loading.attr="disabled" class="focus-ring inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-4 py-2 text-sm font-medium text-[color:var(--color-text-primary)] transition-colors hover:bg-[color:var(--color-surface-hover)] disabled:cursor-not-allowed disabled:opacity-50" aria-label="{{ __('pagination.next_label') }}">
                    {{ __('pagination.next') }}
                </button>
            @else
                <span aria-disabled="true" aria-label="{{ __('pagination.next_label') }}" class="inline-flex min-h-11 cursor-not-allowed items-center rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-4 py-2 text-sm font-medium text-[color:var(--color-text-disabled)] opacity-50">
                    {{ __('pagination.next') }}
                </span>
            @endif
        </div>

        <div class="hidden items-center gap-1 sm:flex">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="{{ __('pagination.previous_label') }}" class="inline-flex min-h-10 cursor-not-allowed items-center rounded-l-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-3 py-2 text-sm text-[color:var(--color-text-disabled)] opacity-50">{{ __('pagination.previous') }}</span>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" @if ($scrollAction) x-on:click="{{ $scrollAction }}" @endif wire:loading.attr="disabled" class="focus-ring inline-flex min-h-10 items-center rounded-l-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-3 py-2 text-sm font-medium text-[color:var(--color-text-primary)] transition-colors hover:bg-[color:var(--color-surface-hover)] disabled:cursor-not-allowed disabled:opacity-50" aria-label="{{ __('pagination.previous_label') }}">{{ __('pagination.previous') }}</button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-hidden="true" class="inline-flex min-h-10 items-center border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-3 py-2 text-sm text-[color:var(--color-text-secondary)]">{{ $element }}</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span aria-current="page" aria-label="{{ __('pagination.current_page', ['page' => $page]) }}" class="inline-flex min-h-10 min-w-10 items-center justify-center border border-[color:var(--color-brand-light)] bg-[color:var(--color-brand)] px-3 py-2 text-sm font-semibold text-white shadow-sm">{{ $page }}</span>
                        @else
                            <button type="button" wire:key="paginator-{{ $paginator->getPageName() }}-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" @if ($scrollAction) x-on:click="{{ $scrollAction }}" @endif wire:loading.attr="disabled" class="focus-ring inline-flex min-h-10 min-w-10 items-center justify-center border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-3 py-2 text-sm font-medium text-[color:var(--color-text-primary)] transition-colors hover:bg-[color:var(--color-surface-hover)] disabled:cursor-not-allowed disabled:opacity-50" aria-label="{{ __('pagination.go_to_page', ['page' => $page]) }}">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" @if ($scrollAction) x-on:click="{{ $scrollAction }}" @endif wire:loading.attr="disabled" class="focus-ring inline-flex min-h-10 items-center rounded-r-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-3 py-2 text-sm font-medium text-[color:var(--color-text-primary)] transition-colors hover:bg-[color:var(--color-surface-hover)] disabled:cursor-not-allowed disabled:opacity-50" aria-label="{{ __('pagination.next_label') }}">{{ __('pagination.next') }}</button>
            @else
                <span aria-disabled="true" aria-label="{{ __('pagination.next_label') }}" class="inline-flex min-h-10 cursor-not-allowed items-center rounded-r-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-3 py-2 text-sm text-[color:var(--color-text-disabled)] opacity-50">{{ __('pagination.next') }}</span>
            @endif
        </div>
    </nav>
@endif
