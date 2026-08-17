<?php

declare(strict_types=1);

namespace App\Livewire\Admin\PermissionRequests;

use App\Actions\PermissionRequests\DecidePermissionRequestAction;
use App\Enums\PermissionRequestStatus;
use App\Exceptions\PermissionRequestTransitionException;
use App\Models\PermissionRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bandeja de solicitudes de acceso.
 *
 * Cuando alguien pide un módulo desde Desktop o Mobile, la solicitud aparece
 * aquí y quien tiene `permission-requests.manage` la resuelve. Hasta ahora esta
 * pantalla sólo existía en CodeRED Mobile: el aviso llegaba al panel y no había
 * dónde atenderlo.
 *
 * Decidir NO se implementa aquí. DecidePermissionRequestAction es la misma que
 * usa la API de administración móvil, y hace lo que esta pantalla no debe
 * repetir: bloquea la fila, concede el permiso antes de marcar la solicitud
 * como aprobada, y rechaza una segunda decisión simultánea.
 */
class Index extends Component
{
    use WithPagination;

    /** Filtro por estado. Se abre en pendientes, que es a lo que se viene. */
    public string $status = 'pending';

    public string $search = '';

    /** Solicitud sobre la que se está decidiendo, para el diálogo de rechazo. */
    public ?int $rejectingId = null;

    public string $rejectionReason = '';

    public function mount(): void
    {
        Gate::authorize('permission-requests.view');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function approve(int $id, DecidePermissionRequestAction $action): void
    {
        $this->decide(
            $id,
            fn (PermissionRequest $solicitud, User $revisor) => $action->approve($solicitud, $revisor),
            'Acceso concedido.',
        );
    }

    public function confirmReject(int $id): void
    {
        $this->authorizeManage();

        $this->rejectingId = $id;
        $this->rejectionReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectionReason = '';
    }

    public function reject(DecidePermissionRequestAction $action): void
    {
        $id = $this->rejectingId;

        if ($id === null) {
            return;
        }

        $motivo = trim($this->rejectionReason);

        $this->decide(
            $id,
            fn (PermissionRequest $solicitud, User $revisor) => $action->reject($solicitud, $revisor, $motivo === '' ? null : $motivo),
            'Solicitud rechazada.',
        );

        $this->cancelReject();
    }

    /**
     * Camino común de las dos decisiones: autorizar, resolver y avisar.
     *
     * @param  callable(PermissionRequest, User): PermissionRequest  $decidir
     */
    private function decide(int $id, callable $decidir, string $mensaje): void
    {
        $revisor = $this->authorizeManage();

        $solicitud = PermissionRequest::query()->find($id);

        if ($solicitud === null) {
            $this->dispatch('toast', type: 'error', message: 'La solicitud ya no existe.');

            return;
        }

        try {
            $decidir($solicitud, $revisor);
        } catch (PermissionRequestTransitionException $exception) {
            // Otro administrador se adelantó. Es información, no un error de
            // quien la está atendiendo ahora.
            $this->dispatch('toast', type: 'warning', message: $exception->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: $mensaje);
    }

    private function authorizeManage(): User
    {
        Gate::authorize('permission-requests.manage');

        /** @var User $revisor */
        $revisor = auth()->user();

        return $revisor;
    }

    public function render(): View
    {
        $solicitudes = PermissionRequest::query()
            ->with(['user', 'reviewer'])
            ->when($this->status !== 'all', fn (Builder $query) => $query->where('status', $this->status))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $termino = '%'.mb_strtolower(trim($this->search)).'%';

                $query->whereHas('user', fn (Builder $usuarios) => $usuarios
                    ->whereRaw('lower(name) like ?', [$termino])
                    ->orWhereRaw('lower(email) like ?', [$termino]));
            })
            // Las pendientes primero y, dentro de ellas, las más antiguas: son
            // las que llevan más tiempo esperando a que alguien las mire.
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderBy('requested_at')
            ->paginate(15);

        return view('livewire.admin.permission-requests.index', [
            'solicitudes' => $solicitudes,
            'pendientes' => PermissionRequest::query()->pending()->count(),
            'canManage' => Gate::allows('permission-requests.manage'),
            'estados' => PermissionRequestStatus::cases(),
        ])->layout('layouts.app', ['pageTitle' => 'Solicitudes de acceso']);
    }
}
