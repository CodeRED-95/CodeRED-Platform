<?php

namespace App\Livewire\Admin\Ruc;

use App\Modules\Ruc\Models\RucRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Records extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $estado = '';

    #[Url]
    public string $condicion = '';

    #[Url]
    public string $departamento = '';

    #[Url]
    public string $provincia = '';

    #[Url]
    public string $distrito = '';

    #[Url]
    public string $ubigeo = '';

    public function mount(): void
    {
        Gate::authorize('ruc.view');
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = RucRecord::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = trim($this->search);
                $query->where(fn (Builder $query) => $query->where('ruc', $term)->orWhereRaw('razon_social ILIKE ?', ['%'.$term.'%']));
            })
            ->when($this->estado !== '', fn (Builder $query) => $query->where('estado', $this->estado))
            ->when($this->condicion !== '', fn (Builder $query) => $query->where('condicion', $this->condicion))
            ->when($this->departamento !== '', fn (Builder $query) => $query->where('departamento', $this->departamento))
            ->when($this->provincia !== '', fn (Builder $query) => $query->where('provincia', $this->provincia))
            ->when($this->distrito !== '', fn (Builder $query) => $query->where('distrito', $this->distrito))
            ->when($this->ubigeo !== '', fn (Builder $query) => $query->where('ubigeo', trim($this->ubigeo)));

        return view('livewire.admin.ruc.records', [
            'records' => $query->orderBy('razon_social')->paginate(25),
            'estados' => ['' => 'Todos'] + RucRecord::query()->whereNotNull('estado')->distinct()->orderBy('estado')->pluck('estado', 'estado')->all(),
            'condiciones' => ['' => 'Todas'] + RucRecord::query()->whereNotNull('condicion')->distinct()->orderBy('condicion')->pluck('condicion', 'condicion')->all(),
        ])->layout('layouts.app', ['pageTitle' => 'Padrón RUC']);
    }
}
