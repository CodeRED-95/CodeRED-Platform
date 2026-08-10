<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ShalomRecordar;

use App\Models\User;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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

    public bool $ownOnly = false;

    public function mount(): void
    {
        $this->ownOnly = auth()->user()?->hasPermission('shalom-recordar.view') !== true;
        abort_unless(auth()->user()?->hasPermission('shalom-recordar.view') || auth()->user()?->hasPermission('shalom-recordar.view-own'), 403);
    }

    public function render(): View
    {
        $users = User::query()
            ->when($this->ownOnly, fn (Builder $query): Builder => $query->whereKey(auth()->id()))
            ->whereHas('shalomRecordarInstallations')
            ->withCount(['shalomRecordarInstallations', 'shalomRecordarRecords'])
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $sub): void {
                $sub->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->latest('updated_at')
            ->paginate($this->perPage);

        $baseQuery = ShalomRecordarRecord::query();
        if ($this->ownOnly) {
            $baseQuery->where('user_id', auth()->id());
        }

        return view('livewire.admin.shalom-recordar.index', [
            'users' => $users,
            'stats' => [
                'users' => $this->ownOnly ? 1 : User::query()->whereHas('shalomRecordarInstallations')->count(),
                'installations' => $this->ownOnly ? ShalomRecordarInstallation::query()->where('user_id', auth()->id())->count() : ShalomRecordarInstallation::query()->count(),
                'records' => $baseQuery->count(),
                'recent_syncs' => $this->ownOnly
                    ? ShalomRecordarInstallation::query()->where('user_id', auth()->id())->where('last_synced_at', '>=', now()->subDays(7))->count()
                    : ShalomRecordarInstallation::query()->where('last_synced_at', '>=', now()->subDays(7))->count(),
            ],
            'ownOnly' => $this->ownOnly,
        ])->layout('layouts.app', ['pageTitle' => 'Shalom Recordar']);
    }
}
