<?php

namespace App\Livewire\Admin\Settings;

use App\Modules\Ruc\Models\Ubigeo;
use App\Modules\Ruc\Services\UbigeoSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Throwable;

class Ubigeos extends Component
{
    public ?array $lastResult = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        Gate::authorize('settings.ubigeos.update');
    }

    public function syncNow(UbigeoSyncService $service): void
    {
        $this->run(fn (): array => $service->sync());
    }

    public function validateCatalog(UbigeoSyncService $service): void
    {
        $this->run(fn (): array => $service->validateCurrent());
    }

    public function restoreSnapshot(UbigeoSyncService $service): void
    {
        $this->run(fn (): array => $service->sync(noDownload: true, force: true));
    }

    public function render(): View
    {
        return view('livewire.admin.settings.ubigeos', [
            'total' => Ubigeo::query()->count(),
            'lastSync' => Ubigeo::query()->max('source_updated_at'),
        ])->layout('layouts.app', ['pageTitle' => 'Ajustes de UBIGEO']);
    }

    private function run(callable $action): void
    {
        Gate::authorize('settings.ubigeos.update');
        $this->errorMessage = null;
        try {
            $this->lastResult = $action();
            $this->dispatch('toast', type: 'success', message: 'Operación de UBIGEO completada.');
        } catch (Throwable $exception) {
            report($exception);
            $this->errorMessage = $exception->getMessage();
        }
    }
}
