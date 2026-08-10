<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ShalomRecordar;

use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class InstallationShow extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public ShalomRecordarInstallation $installation;

    public function mount(ShalomRecordarInstallation $installation): void
    {
        Gate::authorize('shalom-recordar.view');
        $this->installation = $installation->load('user');
    }

    public function render(): View
    {
        $records = ShalomRecordarRecord::query()
            ->where('installation_id', $this->installation->id)
            ->when($this->search !== '', fn ($query) => $query->where(function ($sub): void {
                $sub->where('field', 'like', '%'.$this->search.'%')
                    ->orWhere('value', 'like', '%'.$this->search.'%');
            }))
            ->latest('recorded_at')
            ->paginate(20);

        return view('livewire.admin.shalom-recordar.installation-show', compact('records'))
            ->layout('layouts.app', ['pageTitle' => 'Instalación Shalom Recordar']);
    }
}
