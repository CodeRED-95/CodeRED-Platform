<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ShalomRecordar;

use App\Models\User;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public int $perPage = 15;

    public function mount(): void
    {
        Gate::authorize('shalom-recordar.view');
    }

    public function render(): View
    {
        $users = User::query()
            ->whereHas('shalomRecordarInstallations')
            ->withCount(['shalomRecordarInstallations', 'shalomRecordarRecords'])
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $sub): void {
                $sub->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->latest('updated_at')
            ->paginate($this->perPage);

        return view('livewire.admin.shalom-recordar.index', [
            'users' => $users,
            'stats' => [
                'users' => User::query()->whereHas('shalomRecordarInstallations')->count(),
                'installations' => ShalomRecordarInstallation::query()->count(),
                'records' => ShalomRecordarRecord::query()->count(),
                'recent_syncs' => ShalomRecordarInstallation::query()->where('last_synced_at', '>=', now()->subDays(7))->count(),
            ],
        ])->layout('layouts.app', ['pageTitle' => 'Shalom Recordar']);
    }
}
