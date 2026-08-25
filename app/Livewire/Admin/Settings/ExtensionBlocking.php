<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use App\Modules\ExtensionControl\Models\ExtensionBlockRule;
use App\Modules\ExtensionControl\Services\ExtensionBlockRuleService;
use App\Modules\ExtensionControl\Support\BlockRulePattern;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ExtensionBlocking extends Component
{
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $label = '';

    public string $hostPattern = '*.shalomcontrol.com';

    public string $pathPattern = '/*';

    public string $windowMode = 'allowed';

    public string $timezone = 'America/Lima';

    public bool $isActive = true;

    public string $notes = '';

    public string $bulkStart = '08:00';

    public string $bulkEnd = '20:05';

    /** @var array<int, array{enabled: bool, start: string, end: string}> */
    public array $schedule = [];

    public function mount(): void
    {
        Gate::authorize('settings.extension-blocking.view');
        $this->schedule = $this->blankSchedule();
    }

    public function create(): void
    {
        Gate::authorize('settings.extension-blocking.manage');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $ruleId): void
    {
        Gate::authorize('settings.extension-blocking.manage');
        $rule = ExtensionBlockRule::query()->with('windows')->findOrFail($ruleId);

        $this->editingId = $rule->getKey();
        $this->label = (string) $rule->label;
        $this->hostPattern = (string) $rule->host_pattern;
        $this->pathPattern = (string) $rule->path_pattern;
        $this->windowMode = (string) $rule->window_mode;
        $this->timezone = (string) $rule->timezone;
        $this->isActive = (bool) $rule->is_active;
        $this->notes = (string) ($rule->notes ?? '');
        $this->schedule = $this->blankSchedule();

        foreach ($rule->windows as $window) {
            $this->schedule[(int) $window->day_of_week] = [
                'enabled' => true,
                'start' => substr((string) $window->start_time, 0, 5),
                'end' => substr((string) $window->end_time, 0, 5),
            ];
        }

        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    /**
     * Atajos del enunciado tipico: "Lunes a Sabado 8:00-20:05" en un clic y
     * despues solo se retoca el domingo.
     */
    public function applyRange(string $scope): void
    {
        $days = match ($scope) {
            'weekdays' => [1, 2, 3, 4, 5],
            'monday-saturday' => [1, 2, 3, 4, 5, 6],
            'weekend' => [0, 6],
            'sunday' => [0],
            default => [0, 1, 2, 3, 4, 5, 6],
        };

        foreach ($days as $day) {
            $this->schedule[$day] = ['enabled' => true, 'start' => $this->bulkStart, 'end' => $this->bulkEnd];
        }
    }

    public function clearSchedule(): void
    {
        $this->schedule = $this->blankSchedule();
    }

    public function save(ExtensionBlockRuleService $service): void
    {
        Gate::authorize('settings.extension-blocking.manage');

        $this->hostPattern = BlockRulePattern::normalizeHost($this->hostPattern);
        $this->pathPattern = BlockRulePattern::normalizePath($this->pathPattern);

        $this->validate([
            'label' => ['required', 'string', 'max:120'],
            'hostPattern' => ['required', 'string', 'max:190'],
            'pathPattern' => ['required', 'string', 'max:190'],
            'windowMode' => ['required', 'in:allowed,blocked'],
            'timezone' => ['required', 'timezone'],
            'isActive' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'label' => 'nombre',
            'hostPattern' => 'dominio',
            'pathPattern' => 'ruta',
        ]);

        if (! BlockRulePattern::hostIsAllowed($this->hostPattern)) {
            $this->addError('hostPattern', 'El dominio debe pertenecer a '.BlockRulePattern::ALLOWED_DOMAIN.': la extension no tiene permisos sobre otros sitios.');

            return;
        }

        $windows = $this->normalizedWindows();

        if ($windows === []) {
            $this->addError('schedule', 'Activa al menos un dia con su horario.');

            return;
        }

        foreach ($windows as $window) {
            if ($window['start_time'] >= $window['end_time']) {
                $this->addError('schedule', 'En '.BlockRulePattern::dayLabel($window['day_of_week']).' la hora de inicio debe ser anterior a la de fin.');

                return;
            }
        }

        DB::transaction(function () use ($windows): void {
            $rule = $this->editingId !== null
                ? ExtensionBlockRule::query()->findOrFail($this->editingId)
                : new ExtensionBlockRule(['sort_order' => (int) ExtensionBlockRule::query()->max('sort_order') + 1]);

            $rule->fill([
                'label' => $this->label,
                'host_pattern' => $this->hostPattern,
                'path_pattern' => $this->pathPattern,
                'window_mode' => $this->windowMode,
                'timezone' => $this->timezone,
                'is_active' => $this->isActive,
                'notes' => $this->notes !== '' ? $this->notes : null,
                'updated_by' => auth()->id(),
            ]);

            if ($rule->exists === false) {
                $rule->created_by = auth()->id();
            }

            $rule->save();
            $rule->windows()->delete();
            $rule->windows()->createMany($windows);
        });

        $service->forgetCache();
        $this->resetForm();
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Regla de bloqueo guardada. Las extensiones la aplicaran en su proxima sincronizacion.');
    }

    public function toggleActive(int $ruleId, ExtensionBlockRuleService $service): void
    {
        Gate::authorize('settings.extension-blocking.manage');
        $rule = ExtensionBlockRule::query()->findOrFail($ruleId);
        $rule->update(['is_active' => ! $rule->is_active, 'updated_by' => auth()->id()]);
        $service->forgetCache();
        $this->dispatch('toast', type: 'success', message: $rule->is_active ? 'Regla activada.' : 'Regla desactivada.');
    }

    public function delete(int $ruleId, ExtensionBlockRuleService $service): void
    {
        Gate::authorize('settings.extension-blocking.manage');
        ExtensionBlockRule::query()->findOrFail($ruleId)->delete();
        $service->forgetCache();

        if ($this->editingId === $ruleId) {
            $this->resetForm();
            $this->showForm = false;
        }

        $this->dispatch('toast', type: 'success', message: 'Regla eliminada.');
    }

    public function render(ExtensionBlockRuleService $service): View
    {
        return view('livewire.admin.settings.extension-blocking', [
            'rules' => ExtensionBlockRule::query()->ordered()->with('windows')->get(),
            'days' => BlockRulePattern::DAYS,
            'payloadVersion' => substr($service->payload()['version'], 0, 12),
            'canManage' => Gate::allows('settings.extension-blocking.manage'),
        ])->layout('layouts.app', ['pageTitle' => 'Bloqueo de la extensión']);
    }

    /**
     * @return array<int, array{day_of_week: int, start_time: string, end_time: string}>
     */
    private function normalizedWindows(): array
    {
        $windows = [];

        foreach (array_keys(BlockRulePattern::DAYS) as $day) {
            $row = $this->schedule[$day] ?? null;

            if (! is_array($row) || ! ($row['enabled'] ?? false)) {
                continue;
            }

            $windows[] = [
                'day_of_week' => $day,
                'start_time' => $this->normalizeTime((string) ($row['start'] ?? '')),
                'end_time' => $this->normalizeTime((string) ($row['end'] ?? '')),
            ];
        }

        return $windows;
    }

    private function normalizeTime(string $value): string
    {
        return preg_match('/^\d{2}:\d{2}$/', $value) === 1 ? $value.':00' : '00:00:00';
    }

    /**
     * @return array<int, array{enabled: bool, start: string, end: string}>
     */
    private function blankSchedule(): array
    {
        $schedule = [];

        foreach (array_keys(BlockRulePattern::DAYS) as $day) {
            $schedule[$day] = ['enabled' => false, 'start' => '08:00', 'end' => '20:05'];
        }

        return $schedule;
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->label = '';
        $this->hostPattern = '*.shalomcontrol.com';
        $this->pathPattern = '/*';
        $this->windowMode = 'allowed';
        $this->timezone = 'America/Lima';
        $this->isActive = true;
        $this->notes = '';
        $this->bulkStart = '08:00';
        $this->bulkEnd = '20:05';
        $this->schedule = $this->blankSchedule();
        $this->resetErrorBag();
    }
}
