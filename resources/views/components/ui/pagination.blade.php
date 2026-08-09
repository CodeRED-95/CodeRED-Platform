@props(['paginator', 'scrollTo' => false])

{{--
    Soporta los dos paginadores de Laravel:

    - LengthAwarePaginator: conoce el total, así que puede mostrar
      "Mostrando 1 a 50 de 18.253.941".
    - CursorPaginator (el que usa el padrón RUC): NO conoce el total a
      propósito — evitar el COUNT(*) sobre 18M filas es justo el motivo de
      usarlo. Tampoco tiene firstItem()/lastItem()/total(), ni onEachSide().

    Llamar a esos métodos sobre un CursorPaginator los reenvía a la Collection
    subyacente y revienta con "Method ...Collection::firstItem does not exist",
    devolviendo un HTTP 500 en /admin/ruc en cuanto la tabla tiene registros.
--}}
@if ($paginator->hasPages())
    @php
        $isLengthAware = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    @endphp
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        @if ($isLengthAware)
            <p class="text-sm text-[color:var(--color-text-secondary)]">{{ __('pagination.showing', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</p>
        @else
            <p class="text-sm text-[color:var(--color-text-secondary)]">{{ trans_choice(':count registro|:count registros', $paginator->count(), ['count' => number_format($paginator->count())]) }}</p>
        @endif

        {{-- codered.blade.php numera páginas y necesita $elements/currentPage(),
             que un CursorPaginator no puede proporcionar: para él se usa la
             vista simple de anterior/siguiente, con los mismos estilos. --}}
        {{ $isLengthAware
            ? $paginator->onEachSide(1)->links('vendor.pagination.codered', ['scrollTo' => $scrollTo])
            : $paginator->links('vendor.pagination.codered-simple', ['scrollTo' => $scrollTo]) }}
    </div>
@endif
