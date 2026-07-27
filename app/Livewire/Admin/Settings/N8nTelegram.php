<?php

namespace App\Livewire\Admin\Settings;

use App\Services\Integrations\N8nTelegramTokenSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class N8nTelegram extends Component
{
    public bool $enabled = false;

    public string $sharedSecret = '';

    public string $authorizedTelegramUserIds = '';

    public string $authorizedTelegramChatIds = '';

    public int $defaultExpiresInMinutes = 60;

    public int $maxExpiresInMinutes = 1440;

    public string $allowedAbilities = 'agencies:read';

    public int $maxPendingPerUser = 1;

    public int $cooldownMinutes = 5;

    public int $approvalTimeoutMinutes = 1440;

    public string $webhookUrl = '';

    public bool $notifyOnApproval = true;

    public bool $notifyOnRejection = true;

    public ?int $lastTestStatus = null;

    public function mount(N8nTelegramTokenSettings $settings): void
    {
        Gate::authorize('api-token-requests.configure');
        $this->enabled = (bool) $settings->get('enabled', false);
        $this->authorizedTelegramUserIds = implode('\n', $settings->authorizedUsers());
        $this->authorizedTelegramChatIds = implode('\n', $settings->authorizedChats());
        $this->defaultExpiresInMinutes = (int) $settings->get('default_expires_in_minutes', 60);
        $this->maxExpiresInMinutes = (int) $settings->get('max_expires_in_minutes', 1440);
        $this->allowedAbilities = implode('\n', $settings->allowedAbilities());
        $this->maxPendingPerUser = (int) $settings->get('max_pending_per_user', 1);
        $this->cooldownMinutes = (int) $settings->get('cooldown_minutes', 5);
        $this->approvalTimeoutMinutes = (int) $settings->get('approval_timeout_minutes', 1440);
        $this->webhookUrl = $settings->webhookUrl();
        $this->notifyOnApproval = (bool) $settings->get('notify_on_approval', true);
        $this->notifyOnRejection = (bool) $settings->get('notify_on_rejection', true);
    }

    public function save(N8nTelegramTokenSettings $settings): void
    {
        Gate::authorize('api-token-requests.configure');
        $data = $this->validate(['enabled' => ['boolean'], 'sharedSecret' => ['nullable', 'string', 'min:16', 'max:255'], 'webhookUrl' => ['nullable', 'url', 'max:500'], 'defaultExpiresInMinutes' => ['required', 'integer', 'min:1'], 'maxExpiresInMinutes' => ['required', 'integer', 'min:1', 'gte:defaultExpiresInMinutes'], 'maxPendingPerUser' => ['required', 'integer', 'min:1', 'max:20'], 'cooldownMinutes' => ['required', 'integer', 'min:1', 'max:1440'], 'approvalTimeoutMinutes' => ['required', 'integer', 'min:1', 'max:10080'], 'notifyOnApproval' => ['boolean'], 'notifyOnRejection' => ['boolean']]);
        $settings->save(['enabled' => $data['enabled'], 'shared_secret' => $data['sharedSecret'], 'authorized_telegram_user_ids' => $this->lines($this->authorizedTelegramUserIds), 'authorized_telegram_chat_ids' => $this->lines($this->authorizedTelegramChatIds), 'default_expires_in_minutes' => $data['defaultExpiresInMinutes'], 'max_expires_in_minutes' => $data['maxExpiresInMinutes'], 'allowed_abilities' => $this->lines($this->allowedAbilities), 'max_pending_per_user' => $data['maxPendingPerUser'], 'cooldown_minutes' => $data['cooldownMinutes'], 'approval_timeout_minutes' => $data['approvalTimeoutMinutes'], 'webhook_url' => $data['webhookUrl'] ?? '', 'notify_on_approval' => $data['notifyOnApproval'], 'notify_on_rejection' => $data['notifyOnRejection']]);
        $this->sharedSecret = '';
        $this->dispatch('toast', type: 'success', message: 'Integración n8n y Telegram actualizada.');
    }

    public function test(N8nTelegramTokenSettings $settings): void
    {
        Gate::authorize('api-token-requests.configure');
        $this->lastTestStatus = $settings->testConnection();
    }

    public function render(N8nTelegramTokenSettings $settings): View
    {
        return view('livewire.admin.settings.n8n-telegram', ['secretMasked' => $settings->maskedSecret()])->layout('layouts.app', ['pageTitle' => 'n8n y Telegram']);
    }

    private function lines(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value) ?: [])));
    }
}
