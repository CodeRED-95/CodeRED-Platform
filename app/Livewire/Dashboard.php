<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImportRun;
use App\Policies\UserPolicy;
use App\Services\DashboardMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    #[Url]
    public int $period = 30;

    public string $refreshedAt;

    public function mount(): void
    {
        Gate::authorize('dashboard.view');
        $this->refreshedAt = now()->toIso8601String();
    }

    public function updatedPeriod(): void
    {
        $this->refreshedAt = now()->toIso8601String();
    }

    public function refreshMetrics(DashboardMetricsService $service): void
    {
        $service->flushAll();
        $this->refreshedAt = now()->toIso8601String();
    }

    public function render(DashboardMetricsService $service): View
    {
        $period = $service->normalizePeriod($this->period);
        if ($period !== $this->period) {
            $this->period = $period;
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

        $metrics = $service->metrics($period);

        return view('livewire.dashboard', [
            'period' => $period,
            'refreshedAt' => $this->refreshedAt,
            'canViewAgencies' => $canViewAgencies,
            'canViewUsers' => $canViewUsers,
            'canViewActivity' => $canViewActivity,
            'isSuperAdmin' => $isSuperAdmin,
            'canViewDniMetrics' => $canViewDniMetrics,
            'canViewRucMetrics' => $canViewRucMetrics,
            'recentActivity' => $canViewActivity
                ? $this->recentActivity($canViewUserActivity, $canViewAgencyHistory)
                : new Collection,
            'syncRunsInPeriod' => $canViewAgencies ? $this->syncRunsInPeriod($period) : 0,
            'trendMaximum' => max(collect($metrics['agencyTrend'])->max('count') ?? 0, 1),
            ...$metrics,
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

    private function syncRunsInPeriod(int $period): int
    {
        return AgencyImportRun::query()
            ->where('created_at', '>=', now()->startOfDay()->subDays($period - 1))
            ->count();
    }
}
