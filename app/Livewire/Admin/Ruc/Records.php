<?php

namespace App\Livewire\Admin\Ruc;

use App\Modules\Ruc\Models\RucRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class Records extends Component
{
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

    #[Url]
    public ?string $cursor = null;

    public function mount(): void
    {
        Gate::authorize('ruc.view');
    }

    public function updating($property): void
    {
        // Resetear cursor cuando cambian filtros
        if ($property !== 'cursor') {
            $this->cursor = null;
        }
    }

    public function render(): View
    {
        // Construir query con filtros
        $query = RucRecord::query()
            ->select([
                'id', 'ruc', 'razon_social', 'estado', 'condicion',
                'departamento', 'provincia', 'distrito', 'ubigeo', 'direccion',
            ])
            ->when($this->validateSearchInput(), function (Builder $query): void {
                $term = trim($this->search);
                // Si es RUC exacto (11 dígitos), usar WHERE = con índice unique
                if (preg_match('/^\d{11}$/', $term)) {
                    $query->where('ruc', '=', $term);
                } else {
                    // Búsqueda por razón social usando trigram
                    $query->whereRaw('razon_social ILIKE ?', ['%'.$term.'%']);
                }
            })
            ->when($this->estado !== '', fn (Builder $query) => $query->where('estado', $this->estado))
            ->when($this->condicion !== '', fn (Builder $query) => $query->where('condicion', $this->condicion))
            ->when($this->departamento !== '', fn (Builder $query) => $query->where('departamento', $this->departamento))
            ->when($this->provincia !== '', fn (Builder $query) => $query->where('provincia', $this->provincia))
            ->when($this->distrito !== '', fn (Builder $query) => $query->where('distrito', $this->distrito))
            ->when($this->ubigeo !== '', fn (Builder $query) => $query->where('ubigeo', trim($this->ubigeo)));

        // Cursor pagination por ID (estable y rápido)
        $records = $query
            ->orderBy('id')
            ->cursorPaginate(50, ['*'], 'cursor', $this->cursor);

        return view('livewire.admin.ruc.records', [
            'records' => $records,
            'searchError' => $this->getSearchError(),
            // Filtros hardcodeados (NO HACER DISTINCT sobre tabla)
            'estados' => $this->getEstados(),
            'condiciones' => $this->getCondiciones(),
        ])->layout('layouts.app', ['pageTitle' => 'Padrón RUC']);
    }

    /**
     * Validar que búsqueda no sea demasiado amplia (< 3 caracteres para razón social).
     */
    private function validateSearchInput(): bool
    {
        $term = trim($this->search);
        if ($term === '') {
            return false;
        }

        // RUC exacto siempre permitido (11 dígitos)
        if (preg_match('/^\d{11}$/', $term)) {
            return true;
        }

        // Razón social debe tener al menos 3 caracteres
        return mb_strlen($term) >= 3;
    }

    /**
     * Obtener mensaje de error si búsqueda es demasiado vaga.
     */
    private function getSearchError(): ?string
    {
        $term = trim($this->search);
        if ($term === '') {
            return null;
        }

        // Si no es RUC exacto y < 3 caracteres
        if (! preg_match('/^\d{11}$/', $term) && mb_strlen($term) < 3) {
            return 'Busca por RUC exacto (11 dígitos) o razón social (min 3 caracteres).';
        }

        return null;
    }

    /**
     * Obtener estados disponibles.
     * HARDCODEADO para evitar DISTINCT sobre tabla de 18M filas.
     */
    private function getEstados(): array
    {
        return [
            '' => 'Todos',
            'ACTIVO' => 'Activo',
            'HABIDO' => 'Habido',
            'CANCELADO' => 'Cancelado',
            'SUSPENDIDO' => 'Suspendido',
            'OBSERVADO' => 'Observado',
        ];
    }

    /**
     * Obtener condiciones disponibles.
     * HARDCODEADO para evitar DISTINCT sobre tabla de 18M filas.
     */
    private function getCondiciones(): array
    {
        return [
            '' => 'Todas',
            'PRINCIPAL' => 'Principal',
            'SECUNDARIA' => 'Secundaria',
            'NO DOMICILIADO' => 'No Domiciliado',
        ];
    }
}
