<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Actions\BulkActivateAgenciesAction;
use App\Modules\Agencies\Actions\BulkDeleteAgenciesAction;
use App\Modules\Agencies\Actions\BulkForceDeleteAgenciesAction;
use App\Modules\Agencies\Actions\BulkRestoreAgenciesAction;
use App\Modules\Agencies\Enums\AgencySize;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Services\AgencyBackupService;
use App\Modules\Agencies\Services\AgencySearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    private const MAX_BULK_SELECTION = 100;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $department = '';

    #[Url]
    public string $province = '';

    #[Url]
    public string $district = '';

    #[Url]
    public string $size = '';

    #[Url]
    public string $source = '';

    #[Url]
    public string $operationsCenter = '';

    #[Url]
    public string $moved = '';

    #[Url]
    public string $withoutCoordinates = '';

    #[Url]
    public string $withoutPhone = '';

    #[Url]
    public string $underReview = '';

    #[Url]
    public string $withTrashed = '';

    #[Url]
    public int $perPage = 15;

    public string $sortField = 'updated_at';

    public string $sortDirection = 'desc';

    /** @var array<int, int|string> */
    public array $selectedAgencyIds = [];

    public ?string $pendingBulkAction = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Agency::class);
    }

    public function updating(string $property): void
    {
        if (str_starts_with($property, 'selectedAgencyIds') || $property === 'pendingBulkAction') {
            return;
        }

        $this->clearSelection();
        $this->resetPage();
    }

    public function updatingPaginators(): void
    {
        $this->clearSelection();
    }

    public function sortBy(string $field): void
    {
        $this->clearSelection();
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortField = $field;
        $this->sortDirection = 'asc';
    }

    public function togglePageSelection(AgencySearchService $searchService): void
    {
        $pageIds = $this->currentPageIds($searchService);
        $selected = $this->selectedIds();
        $allSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];
        $this->selectedAgencyIds = $allSelected
            ? array_values(array_diff($selected, $pageIds))
            : array_values(array_unique([...$selected, ...$pageIds]));
        $this->pendingBulkAction = null;
    }

    public function clearSelection(): void
    {
        $this->selectedAgencyIds = [];
        $this->pendingBulkAction = null;
    }

    public function createBackup(AgencyBackupService $service): void
    {
        Gate::authorize('agencies.backup.create');
        $backup = $service->create(auth()->id());
        $this->dispatch('toast', type: 'success', message: 'Copia creada: '.$backup->filename);
    }

    public function prepareBulkAction(string $action): void
    {
        abort_unless(in_array($action, ['activate', 'delete', 'restore', 'force-delete'], true), 404);
        $trashAction = in_array($action, ['restore', 'force-delete'], true);
        abort_unless($trashAction === ($this->withTrashed === 'only'), 404);
        if ($this->selectedIds(true) === []) {
            $this->dispatch('toast', type: 'warning', message: 'Selecciona al menos una agencia.');

            return;
        }
        $this->pendingBulkAction = $action;
    }

    public function activateSelected(BulkActivateAgenciesAction $action): void
    {
        if ($this->pendingBulkAction !== 'activate') {
            $this->dispatch('toast', type: 'danger', message: 'Confirma la activación antes de continuar.');

            return;
        }

        $result = $action->execute($this->selectedIds(true));
        $this->clearSelection();
        $this->dispatch('toast', type: 'success', message: 'Se activaron '.$result['activated'].' agencias. Se ignoraron '.$result['ignored'].' porque no estaban en revisión o ya no existen.');
    }

    public function deleteSelected(BulkDeleteAgenciesAction $action): void
    {
        if ($this->pendingBulkAction !== 'delete') {
            $this->dispatch('toast', type: 'danger', message: 'Confirma la eliminación antes de continuar.');

            return;
        }

        $result = $action->execute($this->selectedIds(true));
        $this->clearSelection();
        $this->dispatch('toast', type: 'success', message: 'Se enviaron '.$result['deleted'].' agencias a la papelera. Se ignoraron '.$result['ignored'].' registros no disponibles.');
    }

    public function restoreSelected(BulkRestoreAgenciesAction $action): void
    {
        if ($this->pendingBulkAction !== 'restore') {
            $this->dispatch('toast', type: 'danger', message: 'Confirma la restauración antes de continuar.');

            return;
        }

        $result = $action->execute($this->selectedIds(true));
        $this->clearSelection();
        $message = 'Se restauraron '.$result['restored'].' agencias.';
        if ($result['conflicts'] > 0 || $result['ignored'] > 0) {
            $message .= ' '.$result['conflicts'].' presentaron conflictos de identidad y '.$result['ignored'].' ya no estaban en papelera.';
        }
        $this->dispatch('toast', type: $result['conflicts'] > 0 ? 'warning' : 'success', message: $message);
    }

    public function forceDeleteSelected(string $confirmation, BulkForceDeleteAgenciesAction $action): void
    {
        if ($this->pendingBulkAction !== 'force-delete') {
            $this->dispatch('toast', type: 'danger', message: 'Confirma la eliminación permanente antes de continuar.');

            return;
        }

        if ($confirmation !== 'ELIMINAR') {
            throw ValidationException::withMessages([
                'selectedAgencyIds' => 'Escribe ELIMINAR exactamente para confirmar.',
            ]);
        }

        $result = $action->execute($this->selectedIds(true));
        $this->clearSelection();
        $this->dispatch('toast', type: 'success', message: 'Se eliminaron definitivamente '.$result['deleted'].' agencias. Se ignoraron '.$result['ignored'].' registros que ya no estaban en papelera.');
    }

    public function deleteAgency(int $agencyId): void
    {
        $agency = Agency::query()->findOrFail($agencyId);
        Gate::authorize('delete', $agency);
        DB::transaction(fn () => $agency->delete());

        $this->dispatch('toast', type: 'success', message: 'La agencia se movió a la papelera.');
    }

    public function restoreAgency(int $agencyId): void
    {
        $agency = Agency::onlyTrashed()->findOrFail($agencyId);
        Gate::authorize('restore', $agency);
        DB::transaction(fn () => $agency->restore());

        $this->dispatch('toast', type: 'success', message: 'La agencia fue restaurada.');
    }

    public function forceDeleteAgency(int $agencyId): void
    {
        $agency = Agency::onlyTrashed()->findOrFail($agencyId);
        Gate::authorize('forceDelete', $agency);
        DB::transaction(fn () => $agency->forceDelete());

        $this->dispatch('toast', type: 'success', message: 'La agencia fue eliminada definitivamente.');
    }

    public function render(AgencySearchService $searchService): View
    {
        $allowedSortFields = ['code', 'name', 'old_name', 'department', 'province', 'district', 'updated_at', 'data_version'];
        if (! in_array($this->sortField, $allowedSortFields, true)) {
            $this->sortField = 'updated_at';
        }

        $filters = $this->filters();

        $agencies = $searchService->adminQuery($filters)
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        $selectedIds = $this->selectedIds();
        $trashView = $this->withTrashed === 'only';
        $selectedAgencies = $trashView
            ? Agency::onlyTrashed()->whereIn('id', $selectedIds)
            : Agency::query()->whereIn('id', $selectedIds);
        $pageIds = $agencies->getCollection()->pluck('id')->map(fn (int $id): int => $id)->all();
        $user = auth()->user();
        $canManageStatus = $user !== null && $user->hasPermission('agencies.manage_status');
        $canDelete = $user !== null && $user->hasPermission('agencies.delete');
        $canRestore = $user !== null && $user->hasPermission('agencies.restore');

        return view('livewire.admin.agencies.index', [
            'agencies' => $agencies,
            'pageIds' => $pageIds,
            'allPageSelected' => $pageIds !== [] && array_diff($pageIds, $selectedIds) === [],
            'bulkSummary' => [
                'selected' => count($selectedIds),
                'reviewable' => (clone $selectedAgencies)->where('status', AgencyStatus::UnderReview->value)->count(),
                'preview_names' => (clone $selectedAgencies)->limit(5)->pluck('name')->all(),
            ],
            'trashView' => $trashView,
            'canBulkActivate' => ! $trashView && $canManageStatus,
            'canBulkDelete' => ! $trashView && $canDelete,
            'canBulkRestore' => $trashView && $canRestore,
            'canBulkForceDelete' => $trashView && $canDelete && $canRestore,
            'stats' => [
                'total' => Agency::query()->count(),
                'active' => Agency::query()->where('status', AgencyStatus::Active)->count(),
                'under_review' => Agency::query()->where('status', AgencyStatus::UnderReview)->count(),
                'moved' => Agency::query()->moved()->count(),
                'operations_centers' => Agency::query()->operationsCenters()->count(),
                'trash' => Agency::onlyTrashed()->count(),
            ],
            'departments' => Agency::withTrashed()->select('department')->distinct()->orderBy('department')->pluck('department'),
            'provinces' => Agency::withTrashed()->select('province')->distinct()->orderBy('province')->pluck('province'),
            'districts' => Agency::withTrashed()->select('district')->distinct()->orderBy('district')->pluck('district'),
            'sizes' => ['' => 'Todos'] + AgencySize::options(),
            'statuses' => ['' => 'Todos'] + AgencyStatus::options(),
            'filteredExportUrl' => route('admin.agencies.export', ['scope' => 'filtered'] + array_filter($filters, fn (string $value): bool => $value !== '')),
            'allExportUrl' => route('admin.agencies.export', ['scope' => 'all']),
        ])->layout('layouts.app', ['pageTitle' => 'Agencias Shalom']);
    }

    /** @return array<string, string> */
    private function filters(): array
    {
        return [
            'search' => $this->search, 'status' => $this->status, 'department' => $this->department,
            'province' => $this->province, 'district' => $this->district, 'size' => $this->size,
            'source' => $this->source, 'operations_center' => $this->operationsCenter, 'moved' => $this->moved,
            'without_coordinates' => $this->withoutCoordinates, 'without_phone' => $this->withoutPhone,
            'under_review' => $this->underReview, 'trash' => $this->withTrashed,
        ];
    }

    /** @return array<int, int> */
    private function currentPageIds(AgencySearchService $searchService): array
    {
        return $searchService->adminQuery($this->filters())
            ->orderBy($this->sortField, $this->sortDirection)
            ->forPage($this->getPage(), $this->perPage)
            ->pluck('id')->map(fn (int $id): int => $id)->all();
    }

    /** @return array<int, int> */
    private function selectedIds(bool $enforceLimit = false): array
    {
        $ids = collect($this->selectedAgencyIds)->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
        if ($enforceLimit && count($ids) > self::MAX_BULK_SELECTION) {
            throw ValidationException::withMessages(['selectedAgencyIds' => 'Solo puedes procesar hasta 100 agencias por operación.']);
        }

        return array_slice($ids, 0, self::MAX_BULK_SELECTION + 1);
    }
}
