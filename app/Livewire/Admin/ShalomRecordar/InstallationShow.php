<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ShalomRecordar;

use App\Core\Audit\AuditLogger;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use App\Modules\ShalomRecordar\Services\ShalomRecordarSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstallationShow extends Component
{
    use WithPagination;

    /** Búsqueda dentro del detalle de un lote (por campo o valor). */
    #[Url]
    public string $search = '';

    /** Filtro por fecha (calendario) para acotar los lotes: Y-m-d. */
    #[Url]
    public string $date = '';

    /** Lote actualmente abierto en el detalle (su clave canónica). */
    #[Url]
    public string $batch = '';

    public ShalomRecordarInstallation $installation;

    public function mount(ShalomRecordarInstallation $installation): void
    {
        $this->authorizeAccess($installation);
        $this->installation = $installation->load('user');
    }

    /**
     * Acceso permitido a un administrador (shalom-recordar.view / .manage) o al
     * dueño de la instalación (shalom-recordar.view-own). Nunca a instalaciones
     * de otro usuario.
     */
    private function authorizeAccess(ShalomRecordarInstallation $installation): void
    {
        $user = auth()->user();
        $allowed = $user?->hasPermission('shalom-recordar.view')
            || ($user?->hasPermission('shalom-recordar.view-own') && $user->id === $installation->user_id);

        abort_unless($allowed, 403);
    }

    /**
     * Gestión (eliminar/exportar) permitida a un administrador con
     * `shalom-recordar.manage` o al propio dueño de la instalación.
     */
    private function canManage(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasPermission('shalom-recordar.manage')
            || ($user?->hasPermission('shalom-recordar.view-own') && $user->id === $this->installation->user_id));
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    public function updatedDate(): void
    {
        // Al cambiar el filtro se cierra el detalle abierto y se vuelve al inicio.
        $this->batch = '';
        $this->resetPage();
    }

    public function clearDate(): void
    {
        $this->date = '';
        $this->batch = '';
        $this->resetPage();
    }

    /**
     * Las claves de lote pueden contener espacios y dos puntos (cuando derivan
     * de `recorded_at::text`), lo que rompería la llamada en `wire:click`. Por
     * eso la vista las pasa en base64 y aquí se decodifican.
     */
    private function decodeKey(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);

        return $decoded === false ? $encoded : $decoded;
    }

    public function viewBatch(string $encodedKey): void
    {
        $this->authorizeAccess($this->installation);
        $this->batch = $this->decodeKey($encodedKey);
        $this->search = '';
        $this->resetPage();
    }

    public function closeBatch(): void
    {
        $this->batch = '';
        $this->search = '';
        $this->resetPage();
    }

    public function deleteSyncBatch(string $encodedKey, AuditLogger $audit): void
    {
        $this->authorizeManage();

        $batchKey = $this->decodeKey($encodedKey);
        $deleted = app(ShalomRecordarSyncService::class)->deleteSyncBatch($this->installation, $batchKey, $audit);

        if ($this->batch === $batchKey) {
            $this->closeBatch();
        }

        // Livewire re-renderiza y recalcula los contadores automáticamente.
        $this->dispatch('toast', type: 'success', message: $deleted.' registros eliminados del lote.');
    }

    public function exportBatch(string $encodedKey): StreamedResponse
    {
        $this->authorizeManage();

        $export = app(ShalomRecordarSyncService::class)->exportBatchToXlsx($this->installation, $this->decodeKey($encodedKey));

        return response()->streamDownload(function () use ($export): void {
            $handle = fopen($export['path'], 'rb');
            if ($handle !== false) {
                fpassthru($handle);
                fclose($handle);
            }
            @unlink($export['path']);
        }, $export['filename'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function revokeInstallationToken(AuditLogger $audit): void
    {
        Gate::authorize('shalom-recordar.manage');

        app(ShalomRecordarSyncService::class)->revokeInstallationToken($this->installation, $audit);
        $this->dispatch('toast', type: 'success', message: 'Token de la instalación revocado.');
    }

    public function deleteInstallationSyncs(AuditLogger $audit): void
    {
        Gate::authorize('shalom-recordar.manage');

        $count = app(ShalomRecordarSyncService::class)->deleteInstallationSyncs($this->installation, $audit);
        $this->closeBatch();
        $this->dispatch('toast', type: 'success', message: $count.' registros eliminados de la instalación.');
    }

    public function deleteInstallation(AuditLogger $audit): void
    {
        Gate::authorize('shalom-recordar.manage');

        app(ShalomRecordarSyncService::class)->deleteInstallation($this->installation, $audit);
        $this->dispatch('toast', type: 'success', message: 'Instalación eliminada.');
        $this->redirectRoute('admin.shalom-recordar.users.show', ['user' => $this->installation->user_id]);
    }

    public function render(): View
    {
        $batchKeySql = ShalomRecordarSyncService::BATCH_KEY_SQL;

        // Lotes de la instalación, opcionalmente acotados por fecha.
        $batches = ShalomRecordarRecord::query()
            ->selectRaw($batchKeySql.' as batch_id')
            ->selectRaw('count(*) as records_count')
            ->selectRaw('min(recorded_at) as first_at')
            ->selectRaw('max(recorded_at) as last_at')
            ->where('installation_id', $this->installation->id)
            ->when($this->date !== '', fn ($query) => $query->whereRaw('recorded_at::date = ?', [$this->date]))
            ->groupByRaw($batchKeySql)
            ->orderByDesc('last_at')
            ->get();

        // Detalle: solo si hay un lote abierto, y solo sus registros.
        $batchRecords = null;
        if ($this->batch !== '') {
            $batchRecords = app(ShalomRecordarSyncService::class)
                ->batchRecordsQuery($this->installation, $this->batch)
                ->when($this->search !== '', fn ($query) => $query->where(function ($sub): void {
                    $sub->where('field', 'like', '%'.$this->search.'%')
                        ->orWhere('value', 'like', '%'.$this->search.'%');
                }))
                ->paginate(20);
        }

        return view('livewire.admin.shalom-recordar.installation-show', [
            'batches' => $batches,
            'batchRecords' => $batchRecords,
            'canManage' => $this->canManage(),
        ])->layout('layouts.app', ['pageTitle' => 'Instalación Shalom Recordar']);
    }
}
