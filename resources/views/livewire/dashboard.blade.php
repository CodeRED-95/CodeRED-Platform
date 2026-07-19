@php
    $trendPoints = collect($agencyTrend)->map(fn ($day) => $day['x'].','.$day['y'])->join(' ');
    $trendTotal = collect($agencyTrend)->sum('count');
    $distributionOffset = 0;
    $activityLabels = [
        'created' => 'creó un registro',
        'updated' => 'actualizó un registro',
        'deleted' => 'eliminó un registro',
        'restored' => 'restauró un registro',
        'roles_updated' => 'actualizó roles',
    ];
@endphp

<div class="mx-auto max-w-[1680px] space-y-8">
    <x-ui.page-header title="Dashboard" subtitle="Indicadores operativos, actividad e importaciones de CodeRED Platform.">
        <x-slot:actions>
            @if ($canViewAgencies)
                <div class="w-48">
                    <x-ui.dropdown-select id="dashboard-period" wire:model.live="period" label="Periodo" :value="$period" :options="[7 => 'Últimos 7 días', 30 => 'Últimos 30 días', 90 => 'Últimos 90 días']" />
                </div>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div wire:loading.delay wire:target="period"><x-ui.skeleton variant="card" :rows="2" /></div>

    @if ($canViewAgencies || $canViewUsers)
        <section aria-labelledby="dashboard-kpis" wire:loading.class="opacity-50" wire:target="period">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[color:var(--color-brand-light)]">Resumen ejecutivo</p>
                    <h2 id="dashboard-kpis" class="mt-1 font-display text-2xl font-semibold">Indicadores principales</h2>
                </div>
                <p class="text-sm text-[color:var(--color-text-muted)]">Datos reales · {{ now()->format('d/m/Y H:i') }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @if ($canViewUsers)
                    <x-ui.stat-card label="Total usuarios" :value="$userMetrics['total']" tone="ivory" href="{{ route('admin.users.index') }}" description="Cuentas registradas">
                        <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7-1v6m3-3h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></x-slot:icon>
                    </x-ui.stat-card>
                    <x-ui.stat-card label="Usuarios nuevos" :value="$userMetrics['new']" tone="brand" description="Creados en los últimos {{ $period }} días">
                        <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8v4l2.5 1.5M21 12a9 9 0 1 1-9-9 9 9 0 0 1 9 9Z" stroke="currentColor" stroke-width="1.8"/></svg></x-slot:icon>
                    </x-ui.stat-card>
                @endif
                @if ($canViewAgencies)
                    <x-ui.stat-card label="Total agencias" :value="$agencyMetrics['total']" tone="info" href="{{ route('admin.agencies.index') }}" description="Registros disponibles"><x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20V8l8-4 8 4v12M9 20v-6h6v6" stroke="currentColor" stroke-width="1.8"/></svg></x-slot:icon></x-ui.stat-card>
                    <x-ui.stat-card label="Agencias activas" :value="$agencyMetrics['active']" tone="success" description="Operando normalmente"><x-slot:icon><x-ui.status-icon status="active" class="size-5" /></x-slot:icon></x-ui.stat-card>
                    <x-ui.stat-card label="Agencias inactivas" :value="$agencyMetrics['inactive']" tone="neutral" description="Fuera de operación"><x-slot:icon><x-ui.status-icon status="inactive" class="size-5" /></x-slot:icon></x-ui.stat-card>
                    <x-ui.stat-card label="Cerradas temporalmente" :value="$agencyMetrics['temporarily_closed']" tone="warning" description="Cierre temporal"><x-slot:icon><x-ui.status-icon status="temporarily_closed" class="size-5" /></x-slot:icon></x-ui.stat-card>
                    <x-ui.stat-card label="En revisión" :value="$agencyMetrics['under_review']" tone="info" href="{{ route('admin.agencies.index', ['status' => 'under_review']) }}" description="Requieren validación"><x-slot:icon><x-ui.status-icon status="under_review" class="size-5" /></x-slot:icon></x-ui.stat-card>
                    <x-ui.stat-card label="Trasladadas" :value="$agencyMetrics['moved']" tone="warning" description="Con nueva ubicación"><x-slot:icon><x-ui.status-icon status="moved" class="size-5" /></x-slot:icon></x-ui.stat-card>
                @endif
            </div>
        </section>
    @else
        <x-ui.empty-state title="No tienes indicadores disponibles" description="Tu cuenta no dispone de permisos para consultar métricas administrativas." icon="—" />
    @endif

    @if ($canViewAgencies)
        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]" wire:loading.class="opacity-50" wire:target="period">
            <x-ui.card aria-labelledby="agency-trend-title">
                <x-ui.section-header title="Tendencia de agencias" description="Altas registradas durante los últimos {{ $period }} días." />
                <h2 id="agency-trend-title" class="sr-only">Tendencia de agencias creadas</h2>
                @if ($trendTotal > 0)
                    <div class="mt-6 overflow-hidden rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.02] p-4">
                        <svg viewBox="0 0 620 220" class="h-64 w-full" role="img" aria-label="{{ $trendTotal }} agencias creadas durante los últimos {{ $period }} días">
                            <g aria-hidden="true" class="stroke-[color:var(--color-border-subtle)]" stroke-width="1">
                                <path d="M40 30H580M40 80H580M40 130H580M40 180H580" />
                                <path d="M40 20V180H590" />
                            </g>
                            <polyline points="{{ $trendPoints }}" fill="none" class="stroke-[color:var(--color-brand-light)]" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                            @foreach ($agencyTrend as $day)
                                <circle cx="{{ $day['x'] }}" cy="{{ $day['y'] }}" r="4" class="fill-[color:var(--color-brand)] stroke-white" stroke-width="2" tabindex="0">
                                    <title>{{ $day['label'] }}: {{ $day['count'] }} agencias</title>
                                </circle>
                            @endforeach
                            <text x="40" y="205" class="fill-[color:var(--color-text-muted)] text-xs">{{ $agencyTrend[0]['label'] }}</text>
                            <text x="310" y="205" text-anchor="middle" class="fill-[color:var(--color-text-muted)] text-xs">{{ $agencyTrend[intdiv(count($agencyTrend), 2)]['label'] }}</text>
                            <text x="580" y="205" text-anchor="end" class="fill-[color:var(--color-text-muted)] text-xs">{{ $agencyTrend[array_key_last($agencyTrend)]['label'] }}</text>
                        </svg>
                    </div>
                @else
                    <div class="mt-6"><x-ui.empty-state title="No hay datos en este periodo" description="No se registraron agencias durante los últimos {{ $period }} días." icon="—" /></div>
                @endif
                <p class="mt-4 text-sm text-[color:var(--color-text-secondary)]">Total del periodo: <strong class="text-white">{{ $trendTotal }}</strong> agencias.</p>
            </x-ui.card>

            <x-ui.card aria-labelledby="status-distribution-title">
                <x-ui.section-header title="Distribución por estado" description="Cantidad y proporción actual." />
                <h2 id="status-distribution-title" class="sr-only">Distribución de agencias por estado</h2>
                <div class="mt-6 grid items-center gap-6 sm:grid-cols-[12rem_1fr] xl:grid-cols-1 2xl:grid-cols-[12rem_1fr]">
                    <div class="relative mx-auto size-48" role="img" aria-label="Distribución de {{ $agencyMetrics['total'] }} agencias por estado">
                        <svg viewBox="0 0 42 42" class="size-full -rotate-90">
                            <circle cx="21" cy="21" r="15.9155" fill="none" class="stroke-white/5" stroke-width="6" />
                            @foreach ($statusDistribution as $status)
                                <circle cx="21" cy="21" r="15.9155" fill="none" class="{{ $status['stroke'] }}" stroke-width="6" pathLength="100" stroke-dasharray="{{ $status['percentage'] }} {{ 100 - $status['percentage'] }}" stroke-dashoffset="-{{ $distributionOffset }}">
                                    <title>{{ $status['label'] }}: {{ $status['count'] }} ({{ number_format($status['percentage'], 1) }}%)</title>
                                </circle>
                                @php($distributionOffset += $status['percentage'])
                            @endforeach
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center"><strong class="text-3xl">{{ $agencyMetrics['total'] }}</strong><span class="text-xs text-[color:var(--color-text-muted)]">agencias</span></div>
                    </div>
                    <ul class="space-y-3">
                        @foreach ($statusDistribution as $status)
                            <li class="flex items-center justify-between gap-3 text-sm"><span class="flex min-w-0 items-center gap-2"><x-ui.status-icon :status="$status['value']" class="size-4 shrink-0" /><span class="truncate">{{ $status['label'] }}</span></span><span class="whitespace-nowrap text-[color:var(--color-text-secondary)]">{{ $status['count'] }} · {{ number_format($status['percentage'], 1) }}%</span></li>
                        @endforeach
                    </ul>
                </div>
            </x-ui.card>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
            <x-ui.card>
                <x-ui.section-header title="Actividad reciente" description="Eventos reales registrados por la auditoría." />
                @if ($canViewActivity)
                    <ol class="mt-5 divide-y divide-[color:var(--color-border-subtle)]">
                        @forelse ($recentActivity as $activity)
                            <li class="flex items-start gap-4 py-4 first:pt-0 last:pb-0">
                                <x-ui.avatar :name="$activity->actor?->name ?? 'Sistema'" size="sm" />
                                <div class="min-w-0 flex-1"><p class="text-sm"><strong>{{ $activity->actor?->name ?? 'Sistema' }}</strong> {{ $activityLabels[$activity->action] ?? str_replace('_', ' ', $activity->action) }}.</p><p class="mt-1 text-xs text-[color:var(--color-text-muted)]">{{ $activity->created_at?->format('d/m/Y H:i') ?? 'Fecha no disponible' }}</p></div>
                                <x-ui.badge tone="neutral">{{ class_basename((string) $activity->auditable_type) ?: 'Sistema' }}</x-ui.badge>
                            </li>
                        @empty
                            <li><x-ui.empty-state title="Sin actividad reciente" description="Los próximos eventos auditados aparecerán aquí." icon="—" /></li>
                        @endforelse
                    </ol>
                @else
                    <div class="mt-5"><x-ui.empty-state title="Actividad restringida" description="Tu cuenta no tiene permiso para consultar eventos de auditoría." icon="—" /></div>
                @endif
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header title="Última importación" description="Resultado del proceso más reciente." />
                @if ($lastImport)
                    <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
                        <div class="col-span-2"><dt class="text-[color:var(--color-text-muted)]">Archivo</dt><dd class="mt-1 break-all font-medium">{{ $lastImport->original_filename }}</dd></div>
                        <div class="col-span-2 flex items-center justify-between gap-4"><dt class="text-[color:var(--color-text-muted)]">Estado</dt><dd><x-ui.badge tone="info">{{ $lastImport->status?->value ?? $lastImport->status }}</x-ui.badge></dd></div>
                        <div><dt class="text-[color:var(--color-text-muted)]">Fecha</dt><dd class="mt-1 font-medium">{{ $lastImport->completed_at?->format('d/m/Y H:i') ?? $lastImport->created_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                        <div><dt class="text-[color:var(--color-text-muted)]">Procesados</dt><dd class="mt-1 text-lg font-semibold">{{ number_format($lastImport->total_rows) }}</dd></div>
                        <div><dt class="text-[color:var(--color-text-muted)]">Importados</dt><dd class="mt-1 font-semibold text-emerald-300">{{ number_format($lastImport->imported_rows) }}</dd></div>
                        <div><dt class="text-[color:var(--color-text-muted)]">Actualizados</dt><dd class="mt-1 font-semibold text-sky-300">{{ number_format($lastImport->updated_rows) }}</dd></div>
                        <div><dt class="text-[color:var(--color-text-muted)]">Ignorados</dt><dd class="mt-1 font-semibold text-amber-200">{{ number_format($lastImport->skipped_rows) }}</dd></div>
                        <div><dt class="text-[color:var(--color-text-muted)]">Errores</dt><dd class="mt-1 font-semibold text-rose-200">{{ number_format($lastImport->failed_rows) }}</dd></div>
                    </dl>
                @else
                    <div class="mt-5"><x-ui.empty-state title="Sin importaciones" description="El primer proceso aparecerá aquí." icon="—" /></div>
                @endif
            </x-ui.card>
        </div>
    @endif
</div>
