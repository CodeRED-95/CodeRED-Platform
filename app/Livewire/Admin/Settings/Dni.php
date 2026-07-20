<?php

namespace App\Livewire\Admin\Settings;

use App\Core\Audit\AuditLogger;
use App\Models\SystemSetting;
use App\Services\Dni\DniCacheService;
use App\Services\Dni\DniSettingsService;
use App\Services\Dni\PeruDevsDniProvider;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dni extends Component
{
    public bool $enabled = false;

    public string $baseUrl = '';

    public string $newApiToken = '';

    public int $timeoutSeconds = 10;

    public int $retryTimes = 2;

    public int $cacheTtlSeconds = 86400;

    public int $notFoundCacheTtlSeconds = 300;

    public bool $persistResults = true;

    public int $refreshAfterDays = 30;

    public string $testDni = '';

    public ?array $testResult = null;

    public function mount(DniSettingsService $settings): void
    {
        Gate::authorize('settings.dni.view');
        $this->enabled = $settings->enabled();
        $this->baseUrl = $settings->baseUrl();
        $this->timeoutSeconds = $settings->timeout();
        $this->retryTimes = $settings->retries();
        $this->cacheTtlSeconds = $settings->cacheTtl();
        $this->notFoundCacheTtlSeconds = $settings->notFoundCacheTtl();
        $this->persistResults = $settings->persistResults();
        $this->refreshAfterDays = $settings->refreshAfterDays();
    }

    public function save(DniSettingsService $settings, AuditLogger $audit): void
    {
        Gate::authorize('settings.dni.update');
        $values = $this->validate($this->rules());
        $settings->save(['enabled' => (bool) $values['enabled'], 'base_url' => trim($values['baseUrl']), 'timeout_seconds' => (int) $values['timeoutSeconds'], 'retry_times' => (int) $values['retryTimes'], 'cache_ttl_seconds' => (int) $values['cacheTtlSeconds'], 'not_found_cache_ttl_seconds' => (int) $values['notFoundCacheTtlSeconds'], 'persist_results' => (bool) $values['persistResults'], 'refresh_after_days' => (int) $values['refreshAfterDays']], $this->newApiToken);
        $this->newApiToken = '';
        $model = SystemSetting::query()->where('key', 'dni_perudevs.enabled')->firstOrFail();
        $audit->log($model, 'dni_settings_updated', [], ['enabled' => $this->enabled, 'base_url' => $this->baseUrl, 'timeout_seconds' => $this->timeoutSeconds, 'retry_times' => $this->retryTimes, 'persist_results' => $this->persistResults], ['enabled', 'base_url', 'timeout_seconds', 'retry_times', 'persist_results', 'credentials']);
        $this->dispatch('toast', type: 'success', message: 'Configuración de PeruDevs guardada.');
    }

    public function testConnection(PeruDevsDniProvider $provider): void
    {
        Gate::authorize('settings.dni.test');
        $this->validate(['testDni' => ['required', 'regex:/^\d{8}$/']]);
        $this->testResult = $provider->testConnection($this->testDni);
        $this->testDni = '';
    }

    public function clearCache(DniCacheService $cache): void
    {
        Gate::authorize('settings.dni.clear-cache');
        $cache->clearAll();
        $this->dispatch('toast', type: 'success', message: 'Caché DNI invalidada.');
    }

    public function deleteToken(DniSettingsService $settings): void
    {
        Gate::authorize('settings.dni.update');
        $settings->deleteToken();
        $this->dispatch('toast', type: 'success', message: 'Token privado de PeruDevs eliminado.');
    }

    public function render(DniSettingsService $settings): View
    {
        return view('livewire.admin.settings.dni', ['tokenMasked' => $settings->maskedToken(), 'tokenConfigured' => $settings->hasApiToken()])->layout('layouts.app', ['pageTitle' => 'API DNI / PeruDevs']);
    }

    private function rules(): array
    {
        return ['enabled' => ['boolean'], 'baseUrl' => ['required', 'url', 'starts_with:https://', 'max:255'], 'newApiToken' => ['nullable', 'string', 'max:1000'], 'timeoutSeconds' => ['required', 'integer', 'min:1', 'max:60'], 'retryTimes' => ['required', 'integer', 'min:0', 'max:5'], 'cacheTtlSeconds' => ['required', 'integer', 'min:60', 'max:604800'], 'notFoundCacheTtlSeconds' => ['required', 'integer', 'min:30', 'max:86400'], 'persistResults' => ['boolean'], 'refreshAfterDays' => ['required', 'integer', 'min:1', 'max:365']];
    }
}
