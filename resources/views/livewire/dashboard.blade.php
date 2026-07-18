@php
    $total = \App\Modules\Agencies\Models\Agency::query()->count();
    $active = \App\Modules\Agencies\Models\Agency::query()->where('status', 'active')->count();
    $review = \App\Modules\Agencies\Models\Agency::query()->where('status', 'under_review')->count();
    $moved = \App\Modules\Agencies\Models\Agency::query()->where('has_moved', true)->count();
    $co = \App\Modules\Agencies\Models\Agency::query()->where('is_operations_center', true)->count();
    $cards = [
        ['label' => 'Total de agencias', 'value' => $total, 'class' => 'text-slate-500'],
        ['label' => 'Activas', 'value' => $active, 'class' => 'text-emerald-500'],
        ['label' => 'En revisión', 'value' => $review, 'class' => 'text-amber-500'],
        ['label' => 'Trasladadas', 'value' => $moved, 'class' => 'text-sky-500'],
        ['label' => 'Centros de operaciones', 'value' => $co, 'class' => 'text-violet-500'],
    ];
@endphp

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach ($cards as $card)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="text-sm text-slate-500">{{ $card['label'] }}</div>
                <div class="mt-2 text-3xl font-semibold {{ $card['class'] }}">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-2xl font-semibold">Dashboard</h2>
        <p class="mt-2 text-slate-500">Base inicial lista para los módulos de CodeRED Platform.</p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('admin.agencies.index') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-slate-900">Administrar agencias</a>
            <a href="{{ route('public.agencies.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm dark:border-slate-700">Vista pública</a>
        </div>
    </div>
</div>
