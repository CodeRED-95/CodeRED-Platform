<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\DniRecord;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImport;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Models\RucRecord;
use App\Policies\UserPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    #[Url]
    public int $period = 30;

    public function mount(): void
    {
        Gate::authorize('dashboard.view');
    }

    public function render(): View
    {
        if (! in_array($this->period, [7, 30, 90], true)) {
            $this->period = 30;
        }

        /** @var User $user */
        $user = auth()->user();
        $canViewAgencies = Gate::allows('viewAny', Agency::class);
        $canViewUsers = app(UserPolicy::class)->viewAny($user);
        $isSuperAdmin = $user->isSuperAdmin();
        $canViewUserActivity = $isSuperAdmin || $user->hasPermission('users.view_activity');
        $canViewAgencyHistory = $isSuperAdmin || $user->hasPermission('agencies.view_history');
        $canViewActivity = $canViewUserActivity || $canViewAgencyHistory;
        $canViewDniMetrics = $isSuperAdmin || $user->hasPermission('dni-records.view');
        $canViewRucMetrics = $isSuperAdmin || $user->hasPermission('ruc.view');
        $agencyMetrics = $canViewAgencies ? $this->agencyMetrics() : [];
        $agencyTrend = $canViewAgencies ? $this->agencyTrend() : [];
        $lastImport = $canViewAgencies ? AgencyImport::query()->latest()->first() : null;

        return view('livewire.dashboard', [
            'canViewAgencies' => $canViewAgencies,
            'canViewUsers' => $canViewUsers,
            'canViewActivity' => $canViewActivity,
            'agencyMetrics' => $agencyMetrics,
            'userMetrics' => $canViewUsers ? $this->userMetrics() : [],
            'statusDistribution' => $canViewAgencies ? $this->statusDistribution($agencyMetrics) : [],
            'agencyTrend' => $agencyTrend,
            'trendMaximum' => max(collect($agencyTrend)->max('count') ?? 0, 1),
            'recentActivity' => $canViewActivity
                ? $this->recentActivity($canViewUserActivity, $canViewAgencyHistory)
                : new Collection,
            'lastImport' => $lastImport,
            'importsInPeriod' => $canViewAgencies ? $this->importsInPeriod() : 0,
            'platformMetrics' => $isSuperAdmin ? $this->platformMetrics() : [],
            'dniMetrics' => $canViewDniMetrics ? $this->dniMetrics() : [],
            'rucMetrics' => $canViewRucMetrics ? $this->rucMetrics() : [],
            'canViewDniMetrics' => $canViewDniMetrics,
            'canViewRucMetrics' => $canViewRucMetrics,
            'isSuperAdmin' => $isSuperAdmin,
            'refreshedAt' => now(),
        ])->layout('layouts.app', ['pageTitle' => 'Dashboard']);
    }

    /** @return Collection<int, ActivityLog> */
    private function recentActivity(bool $canViewUserActivity, bool $canViewAgencyHistory): Collection
    {
        $types = collect([
            $canViewUserActivity ? User::class : null,
            $canViewAgencyHistory ? Agency::class : null,
        ])->filter()->values()->all();

        return ActivityLog::query()
            ->with(['actor:id,name', 'auditable'])
            ->whereIn('auditable_type', $types)
            ->latest('created_at')
            ->limit(6)
            ->get();
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
    private function userMetrics(): array
    {
        $since = now()->startOfDay()->subDays($this->period - 1);
        $metrics = User::query()
            ->selectRaw('COUNT(*) AS total, COUNT(*) FILTER (WHERE created_at >= ?) AS recent', [$since])
            ->first();

        return [
            'total' => (int) $metrics?->getAttribute('total'),
            'new' => (int) $metrics?->getAttribute('recent'),
        ];
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

    /** @return array<int, array{date: string, label: string, count: int, x: float, y: float}> */
    private function agencyTrend(): array
    {
        $start = now()->startOfDay()->subDays($this->period - 1);
        $counts = Agency::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupByRaw('DATE(created_at)')
            ->pluck('aggregate', 'day')
            ->map(fn (mixed $count): int => (int) $count);
        $maximum = max($counts->max() ?? 0, 1);
        $divisor = max($this->period - 1, 1);

        return collect(range(0, $this->period - 1))
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

    private function importsInPeriod(): int
    {
        return AgencyImport::query()
            ->where('created_at', '>=', now()->startOfDay()->subDays($this->period - 1))
            ->count();
    }

    private function platformMetrics(): array
    {
        return Cache::remember('dashboard:platform', 60, fn (): array => [
            'requests_24h' => ApiRequestLog::query()->where('created_at', '>=', now()->subDay())->count(),
            'requests_7d' => ApiRequestLog::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'errors_24h' => ApiRequestLog::query()->where('created_at', '>=', now()->subDay())->where('status_code', '>=', 400)->count(),
            'average_ms' => (int) round((float) ApiRequestLog::query()->where('created_at', '>=', now()->subDay())->avg('response_time_ms')),
            'active_tokens' => ApiToken::query()->whereNull('revoked_at')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
        ]);
    }

    private function dniMetrics(): array
    {
        return Cache::remember('dashboard:dni', 60, fn (): array => [
            'records' => DniRecord::query()->count(),
            'requests_today' => ApiRequestLog::query()->where('service', 'dni')->whereDate('created_at', today())->count(),
            'internal_today' => ApiRequestLog::query()->where('service', 'dni')->whereDate('created_at', today())->where('local_database_hit', true)->count(),
            'provider_today' => ApiRequestLog::query()->where('service', 'dni')->whereDate('created_at', today())->where('provider_called', true)->count(),
        ]);
    }

    private function rucMetrics(): array
    {
        return Cache::remember('dashboard:ruc', 60, function (): array {
            $last = RucImport::query()->latest()->first();

            return ['records' => RucRecord::query()->count(), 'requests_today' => ApiRequestLog::query()->where('service', 'ruc')->whereDate('created_at', today())->count(), 'imports' => RucImport::query()->count(), 'last_import' => $last?->finished_at?->toIso8601String(), 'active_import' => $last?->status->active() ? $last : null];
        });
    }
}
