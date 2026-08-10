<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ShalomRecordar;

use App\Models\User;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UserShow extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public User $user;

    public function mount(User $user): void
    {
        Gate::authorize('shalom-recordar.view');
        $this->user = $user;
    }

    public function render(): View
    {
        $installations = ShalomRecordarInstallation::query()
            ->where('user_id', $this->user->id)
            ->withCount('records')
            ->latest('updated_at')
            ->get();

        $records = ShalomRecordarRecord::query()
            ->where('user_id', $this->user->id)
            ->when($this->search !== '', fn ($query) => $query->where(function ($sub): void {
                $sub->where('field', 'like', '%'.$this->search.'%')
                    ->orWhere('value', 'like', '%'.$this->search.'%');
            }))
            ->latest('recorded_at')
            ->paginate(15);

        return view('livewire.admin.shalom-recordar.user-show', compact('installations', 'records'))
            ->layout('layouts.app', ['pageTitle' => 'Shalom Recordar · '.$this->user->name]);
    }
}
