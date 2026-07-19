<?php

namespace App\Livewire;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImport;
use App\Policies\UserPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    #[Url]
    public int $period = 30;

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
        $agencyMetrics = $canViewAgencies ? $this->agencyMetrics() : [];

        return view('livewire.dashboard', [
            'canViewAgencies' => $canViewAgencies,
            'canViewUsers' => $canViewUsers,
            'canViewActivity' => $canViewActivity,
            'agencyMetrics' => $agencyMetrics,
            'userMetrics' => $canViewUsers ? $this->userMetrics() : [],
            'statusDistribution' => $canViewAgencies ? $this->statusDistribution($agencyMetrics) : [],
            'agencyTrend' => $canViewAgencies ? $this->agencyTrend() : [],
            'recentActivity' => $canViewActivity
                ? $this->recentActivity($canViewUserActivity, $canViewAgencyHistory)
                : new Collection,
            'lastImport' => $canViewAgencies ? AgencyImport::query()->latest()->first() : null,
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
            ->with(['actor', 'auditable'])
            ->whereIn('auditable_type', $types)
            ->latest('created_at')
            ->limit(8)
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
        $since = now()->subDays($this->period);
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
     * @return array<int, array{value: string, label: string, count: int, percentage: float, stroke: string}>
     */
    private function statusDistribution(array $metrics): array
    {
        $total = max($metrics['total'], 1);
        $strokes = [
            AgencyStatus::Active->value => 'stroke-emerald-400',
            AgencyStatus::Inactive->value => 'stroke-slate-400',
            AgencyStatus::TemporarilyClosed->value => 'stroke-amber-400',
            AgencyStatus::UnderReview->value => 'stroke-sky-400',
            AgencyStatus::Moved->value => 'stroke-violet-400',
        ];

        return collect(AgencyStatus::cases())
            ->map(fn (AgencyStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => $metrics[$status->value],
                'percentage' => round(($metrics[$status->value] / $total) * 100, 1),
                'stroke' => $strokes[$status->value],
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
                    'x' => round(40 + (($offset / $divisor) * 540), 2),
                    'y' => round(180 - (($count / $maximum) * 150), 2),
                ];
            })
            ->all();
    }
}
