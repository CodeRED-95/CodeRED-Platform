<?php

namespace App\Livewire\Admin\Settings;

use App\Models\Integration;
use App\Models\IntegrationPairing;
use App\Services\Integrations\IntegrationProtocolService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class N8n extends Component
{
    public ?int $activePairingId = null;

    public ?int $lastTestIntegrationId = null;

    public ?array $lastTestResult = null;

    public function mount(): void
    {
        Gate::authorize('integrations.n8n.manage');
        $this->activePairingId = IntegrationPairing::query()->where('provider', 'n8n')->where('status', 'pending')->where('expires_at', '>', now())->latest()->value('id');
    }

    public function connect(IntegrationProtocolService $protocol): void
    {
        Gate::authorize('integrations.n8n.manage');
        $pairing = $protocol->createPairing('n8n', auth()->id());
        $this->activePairingId = $pairing->id;
        $protocol->log(null, 'Pairing', 'Código de pairing generado para n8n.', ['pair_uuid' => $pairing->pair_uuid], performedBy: auth()->id(), ip: request()->ip(), userAgent: request()->userAgent());
    }

    public function reconnect(int $integrationId, IntegrationProtocolService $protocol): void
    {
        Gate::authorize('integrations.n8n.manage');
        $integration = Integration::query()->where('provider', 'n8n')->findOrFail($integrationId);
        $pairing = $protocol->createPairing('n8n', auth()->id(), $integration);
        $this->activePairingId = $pairing->id;
        $protocol->log($integration, 'Reconnect', 'Código de reconexión generado.', ['pair_uuid' => $pairing->pair_uuid], performedBy: auth()->id(), ip: request()->ip(), userAgent: request()->userAgent());
    }

    public function rotateSecret(int $integrationId, IntegrationProtocolService $protocol): void
    {
        Gate::authorize('integrations.n8n.manage');
        $integration = Integration::query()->where('provider', 'n8n')->findOrFail($integrationId);
        $protocol->createPendingSecret($integration);
        $this->dispatch('toast', type: 'success', message: 'Secreto pendiente generado. El nodo CodeRED debe reclamarlo y confirmar la rotación.');
    }

    public function testConnection(int $integrationId, IntegrationProtocolService $protocol): void
    {
        Gate::authorize('integrations.n8n.manage');
        $integration = Integration::query()->where('provider', 'n8n')->findOrFail($integrationId);
        $this->lastTestIntegrationId = $integration->id;
        $this->lastTestResult = $protocol->challenge($integration);
    }

    public function render(): View
    {
        return view('livewire.admin.settings.n8n', [
            'pairing' => $this->activePairingId ? IntegrationPairing::query()->find($this->activePairingId) : null,
            'integrations' => Integration::query()->where('provider', 'n8n')->withCount(['capabilities', 'services', 'plugins'])->with(['capabilities', 'services', 'plugins', 'logs' => fn ($query) => $query->latest('created_at')->limit(8)])->latest('last_seen_at')->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Integraciones n8n']);
    }
}
