@props(['title', 'subtitle' => null, 'eyebrow' => null, 'actions' => null, 'breadcrumbs' => [], 'badges' => null])

<div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div class="space-y-2">
        @if($breadcrumbs !== [])<x-ui.breadcrumb :items="$breadcrumbs" />@endif
        @if($eyebrow)<p class="text-xs font-semibold uppercase tracking-[0.2em] text-[color:var(--color-brand-light)]">{{ $eyebrow }}</p>@endif
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="type-page-title">{{ $title }}</h1>
            @if($badges)<div class="flex flex-wrap gap-2">{{ $badges }}</div>@endif
        </div>
        @if ($subtitle)
            <p class="max-w-3xl text-sm text-[color:var(--color-text-secondary)]">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($actions)
        <div class="flex flex-wrap items-center gap-3">{{ $actions }}</div>
    @endif
</div>
