<?php

namespace App\Livewire;

use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImport;
use App\Policies\UserPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $canViewAgencies = Gate::allows('viewAny', Agency::class);
        $canViewUsers = app(UserPolicy::class)->viewAny($user);
        $agencyMetrics = $canViewAgencies ? $this->agencyMetrics() : [];

        return view('livewire.dashboard', [
            'canViewAgencies' => $canViewAgencies,
            'canViewUsers' => $canViewUsers,
            'agencyMetrics' => $agencyMetrics,
            'userMetrics' => $canViewUsers ? $this->userMetrics() : [],
            'statusDistribution' => $canViewAgencies ? $this->statusDistribution($agencyMetrics) : [],
            'agencyTrend' => $canViewAgencies ? $this->agencyTrend() : [],
            'recentAgencies' => $canViewAgencies
                ? Agency::query()->latest()->limit(6)->get()
                : new Collection,
            'lastImport' => $canViewAgencies ? AgencyImport::query()->latest()->first() : null,
        ])->layout('layouts.app', ['pageTitle' => 'Dashboard']);
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
        return [
            'total' => User::query()->count(),
            'new' => User::query()->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /** @param array<string, int> $metrics
     * @return array<int, array{value: string, label: string, count: int, percentage: float}>
     */
    private function statusDistribution(array $metrics): array
    {
        $total = max($metrics['total'], 1);

        return collect(AgencyStatus::cases())
            ->map(fn (AgencyStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => $metrics[$status->value],
                'percentage' => round(($metrics[$status->value] / $total) * 100, 1),
            ])
            ->all();
    }

    /** @return array<int, array{date: string, label: string, count: int, percentage: float}> */
    private function agencyTrend(): array
    {
        $start = now()->startOfDay()->subDays(6);
        $counts = Agency::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupByRaw('DATE(created_at)')
            ->pluck('aggregate', 'day')
            ->map(fn (mixed $count): int => (int) $count);
        $maximum = max($counts->max() ?? 0, 1);

        return collect(range(0, 6))
            ->map(function (int $offset) use ($start, $counts, $maximum): array {
                $date = $start->copy()->addDays($offset);
                $count = $counts->get($date->toDateString(), 0);

                return [
                    'date' => $date->toDateString(),
                    'label' => $date->locale('es')->isoFormat('dd D'),
                    'count' => $count,
                    'percentage' => round(($count / $maximum) * 100, 1),
                ];
            })
            ->all();
    }
}
