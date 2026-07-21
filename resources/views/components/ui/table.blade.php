@props(['stickyHeader' => false, 'caption' => null])
<div class="overflow-hidden rounded-[var(--radius-card)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] shadow-[var(--shadow-sm)]">
    <div class="overflow-x-auto">
        <table {{ $attributes->merge(['class' => 'ui-table min-w-full text-left text-sm '.($stickyHeader ? '[&_thead]:sticky [&_thead]:top-0 [&_thead]:z-10' : '')]) }}>
            @if($caption)<caption class="sr-only">{{ $caption }}</caption>@endif
            {{ $slot }}
        </table>
    </div>
</div>
