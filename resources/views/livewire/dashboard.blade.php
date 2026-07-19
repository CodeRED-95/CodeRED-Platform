<div class="space-y-8">
    <x-ui.page-header title="Panel general" subtitle="Una vista clara del estado operativo de CodeRED Platform.">
        <x-slot:actions>
            @can('viewAny', \App\Modules\Agencies\Models\Agency::class)
                <x-ui.button href="{{ route('admin.agencies.index') }}" variant="primary">Administrar agencias</x-ui.button>
            @endcan
            @can('import', \App\Modules\Agencies\Models\Agency::class)
                <x-ui.button href="{{ route('admin.agencies.import') }}" variant="secondary">Importar datos</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($canViewAgencies || $canViewUsers)
        <section aria-labelledby="dashboard-summary">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[color:var(--color-brand-light)]">Resumen</p>
                    <h2 id="dashboard-summary" class="mt-1 text-xl font-semibold">Indicadores principales</h2>
                </div>
                <p class="hidden text-sm text-[color:var(--color-text-muted)] sm:block">Actualizado al cargar esta página</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @if ($canViewUsers)
                    <x-ui.stat-card label="Total usuarios" :value="$userMetrics['total']" tone="ivory" href="{{ route('admin.users.index') }}" description="Cuentas registradas">
                        <x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7-1v6m3-3h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></x-slot:icon>
                    </x-ui.stat-card>
                    <x-ui.stat-card label="Usuarios nuevos" :value="$userMetrics['new']" tone="brand" description="Creados en los últimos 30 días">
                        <x-slot:icon><svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8v4l2.5 1.5M21 12a9 9 0 1 1-9-9 9 9 0 0 1 9 9Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></x-slot:icon>
                    </x-ui.stat-card>
                @endif

                @if ($canViewAgencies)
                    <x-ui.stat-card label="Total agencias" :value="$agencyMetrics['total']" tone="info" href="{{ route('admin.agencies.index') }}" description="Registros disponibles" />
                    <x-ui.stat-card label="Agencias activas" :value="$agencyMetrics['active']" tone="success" description="Operando normalmente"><x-slot:icon><x-ui.status-icon status="active" class="size-6" /></x-slot:icon></x-ui.stat-card>
                    <x-ui.stat-card label="Agencias inactivas" :value="$agencyMetrics['inactive']" tone="neutral" description="Fuera de operación"><x-slot:icon><x-ui.status-icon status="inactive" class="size-6" /></x-slot:icon></x-ui.stat-card>
                    <x-ui.stat-card label="Cerradas temporalmente" :value="$agencyMetrics['temporarily_closed']" tone="warning" description="Cierre con posibilidad de reapertura"><x-slot:icon><x-ui.status-icon status="temporarily_closed" class="size-6" /></x-slot:icon></x-ui.stat-card>
                    <x-ui.stat-card label="Agencias trasladadas" :value="$agencyMetrics['moved']" tone="warning" description="Con nueva ubicación o destino"><x-slot:icon><x-ui.status-icon status="moved" class="size-6" /></x-slot:icon></x-ui.stat-card>
                    <x-ui.stat-card label="Agencias en revisión" :value="$agencyMetrics['under_review']" tone="info" href="{{ route('admin.agencies.index', ['status' => 'under_review']) }}" description="Requieren validación"><x-slot:icon><x-ui.status-icon status="under_review" class="size-6" /></x-slot:icon></x-ui.stat-card>
                @endif
            </div>
        </section>
    @else
        <x-ui.empty-state title="No tienes indicadores disponibles" description="Tu cuenta no dispone de permisos para consultar métricas administrativas." icon="—" />
    @endif

    @if ($canViewAgencies)
        <div class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
            <x-ui.card aria-labelledby="agency-trend-title">
                <x-ui.section-header title="Agencias creadas recientemente" description="Altas registradas durante los últimos 7 días." />
                <h2 id="agency-trend-title" class="sr-only">Tendencia de agencias creadas recientemente</h2>
                <div class="mt-7 flex h-52 items-end gap-2 sm:gap-4" role="img" aria-label="Gráfico de agencias creadas durante los últimos siete días">
                    @foreach ($agencyTrend as $day)
                        <div class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2 text-center">
                            <span class="text-xs font-semibold text-[color:var(--color-text-secondary)]">{{ $day['count'] }}</span>
                            <div class="flex h-36 items-end justify-center rounded-lg bg-white/[0.03] px-1">
                                <div class="w-full max-w-12 rounded-t-lg bg-gradient-to-t from-[color:var(--color-brand)] to-[color:var(--color-brand-light)] transition-all duration-500" style="height: {{ $day['count'] > 0 ? max($day['percentage'], 8) : 2 }}%" title="{{ $day['date'] }}: {{ $day['count'] }}"></div>
                            </div>
                            <span class="truncate text-[11px] uppercase tracking-wide text-[color:var(--color-text-muted)]">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card aria-labelledby="status-distribution-title">
                <x-ui.section-header title="Distribución por estado" description="Proporción actual de agencias." />
                <h2 id="status-distribution-title" class="sr-only">Distribución de agencias por estado</h2>
                <div class="mt-6 space-y-5">
                    @foreach ($statusDistribution as $status)
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                                <span class="flex min-w-0 items-center gap-2 font-medium"><x-ui.status-icon :status="$status['value']" class="size-5 shrink-0" /><span class="truncate">{{ $status['label'] }}</span></span>
                                <span class="text-[color:var(--color-text-secondary)]">{{ $status['count'] }} <span class="sr-only">agencias,</span><span aria-hidden="true">·</span> {{ number_format($status['percentage'], 1) }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-white/5"><div class="h-full rounded-full bg-[color:var(--color-info)] transition-all duration-500" style="width: {{ $status['percentage'] }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
            <x-ui.card>
                <x-ui.section-header title="Agencias recientes" description="Últimos registros incorporados a la plataforma." />
                <div class="mt-5 divide-y divide-[color:var(--color-border-subtle)]">
                    @forelse ($recentAgencies as $agency)
                        <a href="{{ route('admin.agencies.show', $agency) }}" class="focus-ring flex items-center justify-between gap-4 rounded-[var(--radius-control)] px-3 py-4 transition hover:bg-white/5" wire:navigate>
                            <span class="min-w-0"><span class="block truncate font-medium">{{ $agency->name }}</span><span class="mt-1 block truncate text-sm text-[color:var(--color-text-secondary)]">{{ $agency->code }} · {{ $agency->department }} / {{ $agency->province }}</span></span>
                            <x-ui.badge :tone="$agency->status->value === 'active' ? 'success' : ($agency->status->value === 'under_review' ? 'info' : ($agency->status->value === 'moved' ? 'warning' : 'neutral'))">{{ $agency->statusLabel() }}</x-ui.badge>
                        </a>
                    @empty
                        <x-ui.empty-state title="Aún no hay agencias" description="Los registros nuevos aparecerán aquí." icon="⌁" />
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header title="Última importación" description="Estado del proceso más reciente." />
                @if ($lastImport)
                    <dl class="mt-5 space-y-4 text-sm">
                        <div><dt class="text-[color:var(--color-text-muted)]">Archivo</dt><dd class="mt-1 break-all font-medium">{{ $lastImport->original_filename }}</dd></div>
                        <div class="flex items-center justify-between gap-4"><dt class="text-[color:var(--color-text-muted)]">Estado</dt><dd><x-ui.badge tone="info">{{ $lastImport->status?->value ?? $lastImport->status }}</x-ui.badge></dd></div>
                        <div class="flex items-center justify-between gap-4"><dt class="text-[color:var(--color-text-muted)]">Filas procesadas</dt><dd class="font-semibold">{{ number_format($lastImport->imported_rows + $lastImport->updated_rows + $lastImport->skipped_rows) }}</dd></div>
                    </dl>
                @else
                    <div class="mt-5"><x-ui.empty-state title="Sin importaciones" description="El primer proceso aparecerá aquí." icon="⇪" /></div>
                @endif
            </x-ui.card>
        </div>
    @endif
</div>
