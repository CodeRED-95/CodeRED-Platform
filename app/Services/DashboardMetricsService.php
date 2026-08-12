<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\DniRecord;
use App\Models\Integration;
use App\Models\IntegrationLog;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardMetricsService
{
    private const CACHE_TTL_SECONDS = 60;

    private const ALLOWED_PERIODS = [7, 30, 90];

    public function normalizePeriod(int $period): int
    {
        return in_array($period, self::ALLOWED_PERIODS, true) ? $period : 30;
    }

    /**
     * @return array{
     *     agencyMetrics: array<string, int>,
     *     userMetrics: array<string, int>,
     *     agencyTrend: array<int, array{date: string, label: string, count: int, x: float, y: float}>,
     *     statusDistribution: array<int, array{value: string, label: string, count: int, percentage: float, stroke: string, dot: string}>,
     *     platformMetrics: array<string, int>,
     *     dniMetrics: array<string, int>,
     *     rucMetrics: array<string, int|string|null>,
     *     shalomMetrics: array<string, mixed>,
     *     n8nMetrics: array<string, mixed>,
     *     systemHealth: array<string, mixed>
     * }
     */
    public function metrics(int $period): array
    {
        $period = $this->normalizePeriod($period);

        return Cache::remember($this->cacheKey($period), self::CACHE_TTL_SECONDS, function () use ($period): array {
            $agencyMetrics = $this->agencyMetrics();

            return [
                'agencyMetrics' => $agencyMetrics,
                'userMetrics' => $this->userMetrics($period),
                'agencyTrend' => $this->agencyTrend($period),
                'statusDistribution' => $this->statusDistribution($agencyMetrics),
                'platformMetrics' => $this->platformMetrics(),
                'dniMetrics' => $this->dniMetrics(),
                'rucMetrics' => $this->rucMetrics(),
                'shalomMetrics' => $this->shalomMetrics($period),
                'n8nMetrics' => $this->n8nMetrics(),
                'systemHealth' => $this->systemHealth(),
            ];
        });
    }

    public function flush(int $period): void
    {
        Cache::forget($this->cacheKey($this->normalizePeriod($period)));
    }

    public function flushAll(): void
    {
        foreach (self::ALLOWED_PERIODS as $period) {
            Cache::forget($this->cacheKey($period));
        }
    }

    private function cacheKey(int $period): string
    {
        return 'dashboard:metrics:'.$period;
    }

    /** @return array<string, int> */
    private function agencyMetrics(): array
    {
        $counts = Agency::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count);

        return [
            'total' => $counts->sum(),
            'active' => $counts->get(AgencyStatus::Active->value, 0),
            'inactive' => $counts->get(AgencyStatus::Inactive->value, 0),
            'temporarily_closed' => $counts->get(AgencyStatus::TemporarilyClosed->value, 0),
            'under_review' => $counts->get(AgencyStatus::UnderReview->value, 0),
            'moved' => $counts->get(AgencyStatus::Moved->value, 0),
        ];
    }

    /** @return array<string, int> */
    private function userMetrics(int $period): array
    {
        $since = now()->startOfDay()->subDays($period - 1);
        $metrics = User::query()
            ->selectRaw('COUNT(*) AS total, COUNT(*) FILTER (WHERE created_at >= ?) AS recent', [$since])
            ->first();

        $previousSince = now()->startOfDay()->subDays(($period * 2) - 1);
        $previous = User::query()
            ->whereBetween('created_at', [$previousSince, $since->copy()->subSecond()])
            ->count();

        return [
            'total' => (int) $metrics?->getAttribute('total'),
            'new' => (int) $metrics?->getAttribute('recent'),
            'previous_period' => $previous,
        ];
    }

    /**
     * @return array<int, array{date: string, label: string, count: int, x: float, y: float}>
     */
    private function agencyTrend(int $period): array
    {
        $start = now()->startOfDay()->subDays($period - 1);
        $counts = Agency::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupByRaw('DATE(created_at)')
            ->pluck('aggregate', 'day')
            ->map(fn (mixed $count): int => (int) $count);
        $maximum = max($counts->max() ?? 0, 1);
        $divisor = max($period - 1, 1);

        return collect(range(0, $period - 1))
            ->map(function (int $offset) use ($start, $counts, $maximum, $divisor): array {
                $date = $start->copy()->addDays($offset);
                $count = $counts->get($date->toDateString(), 0);

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->locale('es')->isoFormat('D MMM'),
                    'count' => $count,
                    'x' => round(56 + (($offset / $divisor) * 688), 2),
                    'y' => round(205 - (($count / $maximum) * 180), 2),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, int>  $metrics
     * @return array<int, array{value: string, label: string, count: int, percentage: float, stroke: string, dot: string}>
     */
    private function statusDistribution(array $metrics): array
    {
        $total = $metrics['total'];
        $styles = [
            AgencyStatus::Active->value => ['stroke-emerald-400', 'bg-emerald-400'],
            AgencyStatus::Inactive->value => ['stroke-slate-400', 'bg-slate-400'],
            AgencyStatus::TemporarilyClosed->value => ['stroke-amber-400', 'bg-amber-400'],
            AgencyStatus::UnderReview->value => ['stroke-sky-400', 'bg-sky-400'],
            AgencyStatus::Moved->value => ['stroke-violet-400', 'bg-violet-400'],
        ];

        return collect(AgencyStatus::cases())
            ->map(fn (AgencyStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => $metrics[$status->value],
                'percentage' => $total > 0 ? round(($metrics[$status->value] / $total) * 100, 1) : 0.0,
                'stroke' => $styles[$status->value][0],
                'dot' => $styles[$status->value][1],
            ])
            ->all();
    }

    private function platformMetrics(): array
    {
        return [
            'requests_24h' => ApiRequestLog::query()->where('created_at', '>=', now()->subDay())->count(),
            'errors_24h' => ApiRequestLog::query()->where('created_at', '>=', now()->subDay())->where('status_code', '>=', 400)->count(),
            'active_tokens' => ApiToken::query()->whereNull('revoked_at')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'average_ms' => (int) round((float) ApiRequestLog::query()->where('created_at', '>=', now()->subDay())->avg('response_time_ms')),
        ];
    }

    private function dniMetrics(): array
    {
        return [
            'records' => DniRecord::query()->count(),
            'requests_today' => ApiRequestLog::query()->where('service', 'dni')->whereDate('created_at', today())->count(),
            'internal_today' => ApiRequestLog::query()->where('service', 'dni')->whereDate('created_at', today())->where('local_database_hit', true)->count(),
            'provider_today' => ApiRequestLog::query()->where('service', 'dni')->whereDate('created_at', today())->where('provider_called', true)->count(),
        ];
    }

    private function rucMetrics(): array
    {
        $stats = Schema::hasTable('ruc_statistics') ? DB::table('ruc_statistics')->first() : null;
        $lastRestore = RucBackupOperation::latestFinishedRestore();
        $latestBackup = RucBackup::query()->latest('created_at')->first();

        return [
            'records' => (int) data_get($stats, 'total_records', 0),
            'requests_today' => ApiRequestLog::query()->where('service', 'ruc')->whereDate('created_at', today())->count(),
            'last_restore' => data_get($stats, 'last_restore_at'),
            'last_backup' => $latestBackup?->created_at?->toIso8601String(),
            'backups' => RucBackup::query()->count(),
            'active_restore' => $this->activeRestorePayload(),
            'latest_finished_restore' => $lastRestore?->toStatusPayload(),
        ];
    }

    /**
     * @return array{
     *     total_installations: int,
     *     total_records: int,
     *     recent_syncs: int,
     *     latest_syncs: Collection<int, ShalomRecordarInstallation>,
     *     latest_sync_at: string|null
     * }
     */
    private function shalomMetrics(int $period): array
    {
        $installations = ShalomRecordarInstallation::query();
        $latestSyncs = ShalomRecordarInstallation::query()
            ->with('user:id,name,email')
            ->withCount('records')
            ->orderByDesc('last_synced_at')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();
        if (! $latestSyncs instanceof Collection) {
            throw new \RuntimeException('Unexpected collection type while loading Shalom metrics.');
        }

        return [
            'total_installations' => ShalomRecordarInstallation::query()->count(),
            'total_records' => ShalomRecordarRecord::query()->count(),
            'recent_syncs' => $installations->where('last_synced_at', '>=', now()->subDays($period - 1))->count(),
            'latest_syncs' => $latestSyncs,
            'latest_sync_at' => $latestSyncs->first()?->last_synced_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     instances: int,
     *     connected: int,
     *     degraded: int,
     *     capabilities: int,
     *     plugins: int,
     *     last_heartbeat_at: string|null,
     *     latest_instances: Collection<int, Integration>,
     *     recent_logs: Collection<int, IntegrationLog>
     * }
     */
    private function n8nMetrics(): array
    {
        $integrations = Integration::query()->where('provider', 'n8n');
        $latestInstances = Integration::query()
            ->where('provider', 'n8n')
            ->withCount(['capabilities', 'services', 'plugins'])
            ->with(['capabilities', 'services', 'plugins'])
            ->orderByDesc('last_seen_at')
            ->limit(5)
            ->get();
        if (! $latestInstances instanceof Collection) {
            throw new \RuntimeException('Unexpected collection type while loading n8n metrics.');
        }

        return [
            'instances' => $integrations->count(),
            'connected' => (clone $integrations)->where('status', 'connected')->count(),
            'degraded' => (clone $integrations)->whereIn('status', ['degraded', 'waiting_heartbeat'])->count(),
            'capabilities' => Integration::query()->where('provider', 'n8n')->withCount('capabilities')->get()->sum('capabilities_count'),
            'plugins' => Integration::query()->where('provider', 'n8n')->withCount('plugins')->get()->sum('plugins_count'),
            'last_heartbeat_at' => Integration::query()->where('provider', 'n8n')->max('last_seen_at'),
            'latest_instances' => $latestInstances,
            'recent_logs' => IntegrationLog::query()
                ->whereHas('integration', fn ($query) => $query->where('provider', 'n8n'))
                ->latest('created_at')
                ->limit(5)
                ->get(),
        ];
    }

    private function systemHealth(): array
    {
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $activeRestore = RucBackupOperation::activeRestore();
        $latestRestore = RucBackupOperation::latestFinishedRestore();
        $integrationsConnected = Integration::query()->where('provider', 'n8n')->where('status', 'connected')->count();

        return [
            'status' => $activeRestore !== null || $failedJobs > 0 ? ($activeRestore !== null ? 'warning' : 'danger') : 'healthy',
            'queue_pending' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'processed_24h' => null,
            'scheduler_last_run' => null,
            'active_restore' => $activeRestore?->toStatusPayload(),
            'last_restore' => $latestRestore?->toStatusPayload(),
            'integrations_connected' => $integrationsConnected,
        ];
    }

    private function activeRestorePayload(): ?array
    {
        $activeRestore = RucBackupOperation::activeRestore();

        return $activeRestore?->toStatusPayload();
    }
}
