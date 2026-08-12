@php
    $trendPoints = collect($agencyTrend)->map(fn ($day) => $day['x'].','.$day['y'])->join(' ');
    $trendTotal = collect($agencyTrend)->sum('count');
    $trendFirst = $agencyTrend[0] ?? null;
    $trendLast = $agencyTrend[array_key_last($agencyTrend)] ?? null;
    $trendArea = $trendFirst && $trendLast
        ? 'M '.$trendFirst['x'].' 205 L '.collect($agencyTrend)->map(fn ($day) => $day['x'].' '.$day['y'])->join(' L ').' L '.$trendLast['x'].' 205 Z'
        : '';
    $distributionOffset = 0;
    $activityLabels = [
        'created' => 'creó',
        'updated' => 'actualizó',
        'deleted' => 'eliminó',
        'restored' => 'restauró',
        'roles_updated' => 'actualizó los roles de',
    ];
    $syncStatusLabels = [
        'pending' => ['Pendiente', 'neutral'],
        'processing' => ['Procesando', 'info'],
        'ready_for_review' => ['Lista para revisar', 'warning'],
        'confirmed' => ['Confirmada', 'success'],
        'completed' => ['Completada', 'success'],
        'failed' => ['Fallida', 'danger'],
        'cancelled' => ['Cancelada', 'neutral'],
    ];
    $secondaryMetrics = collect([
        $canViewUsers ? ['label' => 'Usuarios nuevos', 'value' => $userMetrics['new'], 'detail' => $period.' días', 'tone' => 'text-[color:var(--color-brand-light)]'] : null,
        $canViewAgencies ? ['label' => 'Agencias inactivas', 'value' => $agencyMetrics['inactive'], 'detail' => 'Estado actual', 'tone' => 'text-slate-300'] : null,
        $canViewAgencies ? ['label' => 'Cierre temporal', 'value' => $agencyMetrics['temporarily_closed'], 'detail' => 'Estado actual', 'tone' => 'text-amber-300'] : null,
        $canViewAgencies ? ['label' => 'Trasladadas', 'value' => $agencyMetrics['moved'], 'detail' => 'Estado actual', 'tone' => 'text-violet-300'] : null,
        $canViewAgencies ? ['label' => 'Pendientes / revisión', 'value' => $agencyMetrics['under_review'], 'detail' => 'Estado actual', 'tone' => 'text-sky-300'] : null,
        $canViewAgencies ? ['label' => 'Última sincronización Shalom', 'value' => $shalomMetrics['latest_sync_at'] ? 1 : 0, 'detail' => $shalomMetrics['latest_sync_at'] ? \Illuminate\Support\Carbon::parse($shalomMetrics['latest_sync_at'])->diffForHumans() : 'Sin sincronizaciones', 'tone' => $shalomMetrics['latest_sync_at'] ? 'text-emerald-300' : 'text-[color:var(--color-text-muted)]'] : null,
    ])->filter()->values();
    $statusStyle = [
        'healthy' => ['text-emerald-300', 'bg-emerald-500/10', 'Bajo control'],
        'warning' => ['text-amber-300', 'bg-amber-500/10', 'Requiere atención'],
        'danger' => ['text-rose-300', 'bg-rose-500/10', 'Requiere intervención'],
    ];
    $healthState = $statusStyle[$systemHealth['status']] ?? $statusStyle['healthy'];
@endphp

<div class="mx-auto max-w-[1680px] space-y-5 overflow-x-clip">
    <x-ui.page-header eyebrow="CENTRO OPERATIVO" title="Dashboard" subtitle="Resumen operativo de usuarios, agencias, RUC, integraciones y actividad del sistema.">
        <x-slot:actions>
            <div class="flex flex-wrap items-end justify-end gap-3">
                <div class="w-full min-w-[11rem] sm:w-56">
                    <x-ui.dropdown-select
                        id="dashboard-period"
                        wire:model.live="period"
                        label="Período"
                        :value="$period"
                        :options="[7 => 'Últimos 7 días', 30 => 'Últimos 30 días', 90 => 'Últimos 90 días']"
                    />
                </div>
                <div class="flex items-end gap-3">
                    <p class="pb-3 text-xs text-[color:var(--color-text-muted)]">
                        Actualizado
                        <time datetime="{{ $refreshedAt }}">{{ \Illuminate\Support\Carbon::parse($refreshedAt)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time>
                    </p>
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        wire:click="refreshMetrics"
                        loading-target="refreshMetrics"
                    >
                        Actualizar
                    </x-ui.button>
                </div>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="hidden items-center gap-3 rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.03] px-4 py-3 text-sm text-[color:var(--color-text-secondary)]" wire:loading.delay.flex wire:target="period,refreshMetrics" role="status" aria-live="polite">
        <x-ui.spinner size="sm" label="Actualizando indicadores" />
        <span>Actualizando datos del período…</span>
    </div>

    @if ($canViewAgencies || $canViewUsers)
        <section aria-labelledby="dashboard-primary-metrics" wire:loading.class="opacity-60" wire:target="period,refreshMetrics">
            <h2 id="dashboard-primary-metrics" class="sr-only">Indicadores principales</h2>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @if ($canViewAgencies)
                    <x-ui.stat-card label="Total de agencias" :value="$agencyMetrics['total']" tone="info" href="{{ route('admin.agencies.index') }}" description="Cantidad total real">
                        <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20V8l8-4 8 4v12M9 20v-6h6v6" stroke="currentColor" stroke-width="1.8"/></svg></x-slot:icon>
                    </x-ui.stat-card>
                    <x-ui.stat-card label="Agencias activas" :value="$agencyMetrics['active']" tone="success" href="{{ route('admin.agencies.index', ['status' => 'active']) }}" description="Operativas actualmente">
                        <x-slot:icon><x-ui.status-icon status="active" class="size-5" /></x-slot:icon>
                    </x-ui.stat-card>
                    <x-ui.stat-card label="Agencias en revisión" :value="$agencyMetrics['under_review']" tone="warning" href="{{ route('admin.agencies.index', ['status' => 'under_review']) }}" description="Pendientes de revisión/validación">
                        <x-slot:icon><x-ui.status-icon status="under_review" class="size-5" /></x-slot:icon>
                    </x-ui.stat-card>
                @endif
                @if ($canViewUsers)
                    <x-ui.stat-card label="Total de usuarios" :value="$userMetrics['total']" tone="purple" href="{{ route('admin.users.index') }}" description="Usuarios registrados">
                        <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7-1v6m3-3h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></x-slot:icon>
                    </x-ui.stat-card>
                @endif
            </div>
        </section>

        @if ($secondaryMetrics->isNotEmpty())
            <section aria-labelledby="dashboard-secondary-metrics">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <h2 id="dashboard-secondary-metrics" class="text-sm font-semibold text-white">Resumen secundario</h2>
                    <span class="text-xs text-[color:var(--color-text-muted)]">Período: {{ $period }} días</span>
                </div>
                <dl class="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
                    @foreach ($secondaryMetrics as $metric)
                        <div class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.025] px-3 py-3">
                            <dt class="truncate text-xs text-[color:var(--color-text-muted)]">{{ $metric['label'] }}</dt>
                            <dd class="mt-1 flex items-baseline justify-between gap-2">
                                <strong class="text-xl font-semibold {{ $metric['tone'] }}">{{ is_numeric($metric['value']) ? number_format((int) $metric['value']) : $metric['value'] }}</strong>
                                <span class="text-[0.6875rem] text-[color:var(--color-text-muted)]">{{ $metric['detail'] }}</span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        <section aria-labelledby="dashboard-identity">
            <h2 id="dashboard-identity" class="mb-3 text-sm font-semibold text-white">Identidad, empresas y plataforma</h2>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @if($canViewDniMetrics)
                    <x-ui.stat-card label="Registros DNI internos" :value="$dniMetrics['records']" tone="info" description="Consultas hoy: {{ $dniMetrics['requests_today'] }}" />
                    <x-ui.stat-card label="DNI resueltos internamente" :value="$dniMetrics['internal_today']" tone="success" description="Provider hoy: {{ $dniMetrics['provider_today'] }}" />
                @endif
                @if($canViewRucMetrics)
                    <x-ui.stat-card label="Registros RUC" :value="$rucMetrics['records']" tone="brand" href="{{ route('admin.ruc.records') }}" description="Total persistido en ruc_statistics" />
                    <x-ui.stat-card label="Backups RUC" :value="$rucMetrics['backups']" tone="info" href="{{ route('admin.ruc.backups') }}" description="Última restauración: {{ $rucMetrics['last_restore'] ? \Illuminate\Support\Carbon::parse($rucMetrics['last_restore'])->diffForHumans() : 'Sin restauraciones' }}" />
                @endif
                @if($isSuperAdmin)
                    <x-ui.stat-card label="Solicitudes API · 24 h" :value="$platformMetrics['requests_24h']" tone="purple" description="Errores: {{ $platformMetrics['errors_24h'] }}" />
                    <x-ui.stat-card label="Tokens activos" :value="$platformMetrics['active_tokens']" tone="warning" description="Promedio {{ $platformMetrics['average_ms'] }} ms" />
                @endif
            </div>
        </section>
    @else
        <x-ui.empty-state title="No tienes indicadores disponibles" description="Tu cuenta no dispone de permisos para consultar métricas administrativas." icon="—" />
    @endif

    @if ($canViewAgencies)
        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.85fr)_minmax(19rem,1fr)]" aria-label="Visualizaciones de agencias" wire:loading.class="opacity-60" wire:target="period,refreshMetrics">
            <x-ui.card padding="p-5" aria-labelledby="agency-trend-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="agency-trend-title" class="font-display text-lg font-semibold text-white">Tendencia de agencias</h2>
                        <p class="mt-1 text-xs text-[color:var(--color-text-secondary)]">Altas diarias durante los últimos {{ $period }} días.</p>
                    </div>
                    <div class="text-right">
                        <strong class="text-2xl font-semibold text-white">{{ number_format($trendTotal) }}</strong>
                        <p class="text-xs text-[color:var(--color-text-muted)]">creadas en el período</p>
                    </div>
                </div>

                @if ($trendTotal > 0)
                    <div class="mt-4 h-[280px] min-w-0" role="img" aria-label="{{ $trendTotal }} agencias creadas durante los últimos {{ $period }} días. Máximo diario: {{ $trendMaximum }}.">
                        <svg viewBox="0 0 800 250" class="h-full w-full" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="dashboard-trend-area" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="var(--color-brand)" stop-opacity="0.28" />
                                    <stop offset="100%" stop-color="var(--color-brand)" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <g aria-hidden="true" fill="none" class="stroke-[color:var(--color-border-subtle)]" stroke-width="1">
                                <path d="M56 25H744" />
                                <path d="M56 115H744" />
                                <path d="M56 205H744" />
                                <path d="M56 25V205H744" />
                            </g>
                            <g aria-hidden="true" class="fill-[color:var(--color-text-muted)] text-[11px]">
                                <text x="46" y="209" text-anchor="end">0</text>
                                <text x="46" y="119" text-anchor="end">{{ (int) ceil($trendMaximum / 2) }}</text>
                                <text x="46" y="29" text-anchor="end">{{ $trendMaximum }}</text>
                            </g>
                            <path d="{{ $trendArea }}" fill="url(#dashboard-trend-area)" stroke="none" />
                            <polyline points="{{ $trendPoints }}" fill="none" class="stroke-[color:var(--color-brand-light)]" stroke-width="3" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                            @foreach ($agencyTrend as $day)
                                <circle cx="{{ $day['x'] }}" cy="{{ $day['y'] }}" r="2.5" class="fill-[color:var(--color-brand-light)]" stroke="var(--color-background-elevated)" stroke-width="1.5" vector-effect="non-scaling-stroke">
                                    <title>{{ $day['label'] }}: {{ $day['count'] }} {{ $day['count'] === 1 ? 'agencia' : 'agencias' }}</title>
                                </circle>
                            @endforeach
                            <g aria-hidden="true" class="fill-[color:var(--color-text-muted)] text-[11px]">
                                <text x="56" y="232">{{ $agencyTrend[0]['label'] }}</text>
                                <text x="400" y="232" text-anchor="middle">{{ $agencyTrend[intdiv(count($agencyTrend), 2)]['label'] }}</text>
                                <text x="744" y="232" text-anchor="end">{{ $agencyTrend[array_key_last($agencyTrend)]['label'] }}</text>
                            </g>
                        </svg>
                    </div>
                @else
                    <div class="mt-4 flex h-[280px] items-center justify-center rounded-[var(--radius-control)] border border-dashed border-[color:var(--color-border-subtle)] bg-white/[0.015] px-4 text-center">
                        <div>
                            <p class="text-sm font-medium text-white">No se registraron agencias durante este período.</p>
                            <p class="mt-1 text-xs text-[color:var(--color-text-muted)]">Prueba otro rango para consultar la tendencia histórica.</p>
                        </div>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card padding="p-5" aria-labelledby="status-distribution-title">
                <div>
                    <h2 id="status-distribution-title" class="font-display text-lg font-semibold text-white">Distribución por estado</h2>
                    <p class="mt-1 text-xs text-[color:var(--color-text-secondary)]">Composición real de agencias.</p>
                </div>

                <div class="mt-4 grid items-center gap-4 sm:grid-cols-[9.5rem_1fr] xl:grid-cols-1 2xl:grid-cols-[9.5rem_1fr]">
                    <div class="relative mx-auto size-40" role="img" aria-label="Distribución de {{ $agencyMetrics['total'] }} agencias por estado">
                        <svg viewBox="0 0 42 42" class="size-full -rotate-90" aria-hidden="true">
                            <circle cx="21" cy="21" r="15.9155" fill="none" class="stroke-white/5" stroke-width="5" />
                            @foreach ($statusDistribution as $status)
                                @if ($status['percentage'] > 0)
                                    <circle
                                        cx="21"
                                        cy="21"
                                        r="15.9155"
                                        fill="none"
                                        class="{{ $status['stroke'] }}"
                                        stroke-width="5"
                                        pathLength="100"
                                        stroke-dasharray="{{ $status['percentage'] }} {{ 100 - $status['percentage'] }}"
                                        stroke-dashoffset="-{{ $distributionOffset }}"
                                    >
                                        <title>{{ $status['label'] }}: {{ $status['count'] }} ({{ number_format($status['percentage'], 1) }}%)</title>
                                    </circle>
                                    @php $distributionOffset += $status['percentage']; @endphp
                                @endif
                            @endforeach
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                            <strong class="text-2xl font-semibold text-white">{{ number_format($agencyMetrics['total']) }}</strong>
                            <span class="text-[0.6875rem] text-[color:var(--color-text-muted)]">agencias</span>
                        </div>
                    </div>

                    <ul class="space-y-1.5" aria-label="Detalle de distribución por estado">
                        @foreach ($statusDistribution as $status)
                            <li class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-2 rounded-lg px-2 py-1.5 text-xs {{ $status['count'] === 0 ? 'opacity-55' : '' }}">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="size-2 shrink-0 rounded-full {{ $status['dot'] }}" aria-hidden="true"></span>
                                    <span class="truncate text-[color:var(--color-text-secondary)]">{{ $status['label'] }}</span>
                                </span>
                                <strong class="tabular-nums text-white">{{ $status['count'] }}</strong>
                                <span class="w-12 text-right tabular-nums text-[color:var(--color-text-muted)]">{{ number_format($status['percentage'], 1) }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </x-ui.card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]" aria-label="Estado operativo">
            <x-ui.card padding="p-5" aria-labelledby="system-health-title">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="system-health-title" class="font-display text-lg font-semibold text-white">Salud del sistema</h2>
                        <p class="mt-1 text-xs text-[color:var(--color-text-secondary)]">Estado general y señales operativas seguras.</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $healthState[1] }} {{ $healthState[0] }}">{{ $healthState[2] }}</span>
                </div>

                <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.02] p-3">
                        <dt class="text-xs text-[color:var(--color-text-muted)]">Colas pendientes</dt>
                        <dd class="mt-1 text-2xl font-semibold text-white">{{ number_format((int) ($systemHealth['queue_pending'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.02] p-3">
                        <dt class="text-xs text-[color:var(--color-text-muted)]">Jobs fallidos</dt>
                        <dd class="mt-1 text-2xl font-semibold text-white">{{ number_format((int) ($systemHealth['failed_jobs'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.02] p-3">
                        <dt class="text-xs text-[color:var(--color-text-muted)]">Integraciones conectadas</dt>
                        <dd class="mt-1 text-2xl font-semibold text-white">{{ number_format((int) ($systemHealth['integrations_connected'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.02] p-3">
                        <dt class="text-xs text-[color:var(--color-text-muted)]">Último restore RUC</dt>
                        <dd class="mt-1 text-sm font-medium text-white">{{ data_get($systemHealth, 'last_restore.finished_at') ? \Illuminate\Support\Carbon::parse(data_get($systemHealth, 'last_restore.finished_at'))->diffForHumans() : 'Sin datos' }}</dd>
                    </div>
                    <div class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.02] p-3">
                        <dt class="text-xs text-[color:var(--color-text-muted)]">Último scheduler</dt>
                        <dd class="mt-1 text-sm font-medium text-white">{{ $systemHealth['scheduler_last_run'] ? \Illuminate\Support\Carbon::parse($systemHealth['scheduler_last_run'])->diffForHumans() : 'No disponible' }}</dd>
                    </div>
                    <div class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.02] p-3">
                        <dt class="text-xs text-[color:var(--color-text-muted)]">Procesados 24 h</dt>
                        <dd class="mt-1 text-sm font-medium text-white">{{ $systemHealth['processed_24h'] !== null ? number_format((int) $systemHealth['processed_24h']) : 'N/D' }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card padding="p-5" aria-labelledby="shalom-sync-title">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="shalom-sync-title" class="font-display text-lg font-semibold text-white">Últimas sincronizaciones Shalom</h2>
                        <p class="mt-1 text-xs text-[color:var(--color-text-secondary)]">Resultado del proceso más reciente.</p>
                    </div>
                    <x-ui.button href="{{ route('admin.shalom-recordar.index') }}" variant="secondary" class="shrink-0">Ver historial</x-ui.button>
                </div>

                @if ($shalomMetrics['latest_syncs']->isNotEmpty())
                    <div class="mt-4 space-y-3">
                        @foreach ($shalomMetrics['latest_syncs'] as $sync)
                            @php $syncStatus = $syncStatusLabels[(string) ($sync->last_synced_at ? 'completed' : 'pending')] ?? ['Desconocida', 'neutral']; @endphp
                            <div class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.02] p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-white" title="{{ $sync->user?->name ?? $sync->installation_uuid }}">
                                            {{ $sync->user?->name ?? 'Instalación '.$sync->installation_uuid }}
                                        </p>
                                        <p class="mt-1 text-xs text-[color:var(--color-text-muted)]">
                                            {{ $sync->last_synced_at ? $sync->last_synced_at->format('d/m/Y H:i') : 'Sin sincronización' }} · {{ $sync->installation_uuid }}
                                        </p>
                                    </div>
                                    <x-ui.badge :tone="$syncStatus[1]">{{ $syncStatus[0] }}</x-ui.badge>
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <div><dt class="text-[0.6875rem] text-[color:var(--color-text-muted)]">Registros</dt><dd class="text-sm font-semibold text-white">{{ number_format((int) $sync->records_count) }}</dd></div>
                                    <div><dt class="text-[0.6875rem] text-[color:var(--color-text-muted)]">Última versión</dt><dd class="text-sm font-semibold text-white">{{ $sync->extension_version ?? '—' }}</dd></div>
                                    <div><dt class="text-[0.6875rem] text-[color:var(--color-text-muted)]">Cursor</dt><dd class="truncate text-sm font-semibold text-white">{{ $sync->last_sync_cursor ?? '—' }}</dd></div>
                                    <div><dt class="text-[0.6875rem] text-[color:var(--color-text-muted)]">Actualizado</dt><dd class="text-sm font-semibold text-white">{{ $sync->last_synced_at?->diffForHumans() ?? '—' }}</dd></div>
                                </dl>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-4">
                        <x-ui.empty-state title="No existen sincronizaciones." description="La próxima ejecución aparecerá aquí." icon="⌁" />
                    </div>
                @endif
            </x-ui.card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(18rem,1fr)]" aria-label="Integraciones y actividad">
            <x-ui.card padding="p-5" aria-labelledby="n8n-title">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="n8n-title" class="font-display text-lg font-semibold text-white">Integraciones n8n</h2>
                        <p class="mt-1 text-xs text-[color:var(--color-text-secondary)]">Resumen operativo de instancias conectadas.</p>
                    </div>
                    <x-ui.badge tone="{{ ($n8nMetrics['connected'] ?? 0) > 0 ? 'success' : 'neutral' }}">
                        {{ number_format((int) ($n8nMetrics['instances'] ?? 0)) }} instancias
                    </x-ui.badge>
                </div>

                @if (($n8nMetrics['latest_instances'] ?? collect())->isNotEmpty())
                    <div class="mt-4 space-y-3">
                        @foreach ($n8nMetrics['latest_instances'] as $integration)
                            <div class="rounded-[var(--radius-control)] border border-[color:var(--color-border-subtle)] bg-white/[0.02] p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-white">{{ $integration->instance_name }}</p>
                                        <p class="mt-1 text-xs text-[color:var(--color-text-muted)]">{{ $integration->instance_url }} · {{ $integration->version }}</p>
                                    </div>
                                    <x-ui.badge tone="{{ $integration->connectionStatus() === 'connected' ? 'success' : 'warning' }}">{{ $integration->connectionLabel() }}</x-ui.badge>
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <div><dt class="text-[0.6875rem] text-[color:var(--color-text-muted)]">UUID</dt><dd class="truncate text-sm font-semibold text-white">{{ $integration->integration_uuid }}</dd></div>
                                    <div><dt class="text-[0.6875rem] text-[color:var(--color-text-muted)]">Capabilities</dt><dd class="text-sm font-semibold text-white">{{ number_format((int) $integration->capabilities_count) }}</dd></div>
                                    <div><dt class="text-[0.6875rem] text-[color:var(--color-text-muted)]">Plugins</dt><dd class="text-sm font-semibold text-white">{{ number_format((int) $integration->plugins_count) }}</dd></div>
                                    <div><dt class="text-[0.6875rem] text-[color:var(--color-text-muted)]">Heartbeat</dt><dd class="text-sm font-semibold text-white">{{ $integration->last_seen_at?->diffForHumans() ?? '—' }}</dd></div>
                                </dl>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-4">
                        <x-ui.empty-state title="Sin integraciones n8n" description="Cuando se conecten aparecerán aquí." icon="⌁" />
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card padding="p-5" aria-labelledby="recent-activity-title">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="recent-activity-title" class="font-display text-lg font-semibold text-white">Actividad reciente</h2>
                        <p class="mt-1 text-xs text-[color:var(--color-text-secondary)]">Últimas acciones relevantes del sistema.</p>
                    </div>
                    <x-ui.badge tone="neutral">Máximo 6</x-ui.badge>
                </div>

                @if ($canViewActivity)
                    <ol class="mt-3 divide-y divide-[color:var(--color-border-subtle)]">
                        @forelse ($recentActivity as $activity)
                            @php
                                $target = match (true) {
                                    $activity->auditable instanceof \App\Modules\Agencies\Models\Agency => 'la agencia “'.$activity->auditable->name.'”',
                                    $activity->auditable instanceof \App\Models\User => 'el usuario “'.$activity->auditable->name.'”',
                                    default => 'el registro #'.$activity->auditable_id,
                                };
                                $module = $activity->auditable_type === \App\Models\User::class ? 'Usuarios' : 'Agencias';
                            @endphp
                            <li class="flex items-center gap-3 py-3 first:pt-1 last:pb-1">
                                <x-ui.avatar :name="$activity->actor?->name ?? 'Sistema'" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-[color:var(--color-text-primary)]">
                                        <strong>{{ $activity->actor?->name ?? 'Sistema' }}</strong>
                                        {{ $activityLabels[$activity->action] ?? str_replace('_', ' ', $activity->action) }} {{ $target }}.
                                    </p>
                                    <p class="mt-0.5 text-xs text-[color:var(--color-text-muted)]">
                                        {{ $activity->created_at?->diffForHumans() ?? 'Fecha no disponible' }}
                                    </p>
                                </div>
                                <x-ui.badge tone="neutral" class="hidden sm:inline-flex">{{ $module }}</x-ui.badge>
                            </li>
                        @empty
                            <li class="py-8 text-center text-sm text-[color:var(--color-text-muted)]">Todavía no existe actividad reciente.</li>
                        @endforelse
                    </ol>
                @else
                    <div class="mt-4 rounded-[var(--radius-control)] border border-dashed border-[color:var(--color-border-subtle)] px-4 py-8 text-center text-sm text-[color:var(--color-text-muted)]">Tu cuenta no tiene permiso para consultar la actividad.</div>
                @endif
            </x-ui.card>
        </section>
    @endif
</div>
