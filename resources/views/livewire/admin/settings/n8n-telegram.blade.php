<div class="space-y-6">
    <x-ui.page-header title="Integraciones > n8n y Telegram" subtitle="Solicitudes de tokens API aprobadas manualmente." />
    <x-ui.card title="Seguridad de integración" description="El secreto completo no se muestra después de guardarlo.">
        <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
            <x-ui.toggle wire:model="enabled" label="Integración activa" />
            <div><x-ui.input wire:model="sharedSecret" label="Secreto compartido con n8n" type="password" autocomplete="new-password" :error="$errors->first('sharedSecret')" /><p class="mt-1 text-xs text-[color:var(--color-text-muted)]">{{ $secretMasked }}</p></div>
            <x-ui.textarea wire:model="authorizedTelegramUserIds" label="IDs de usuarios de Telegram autorizados" rows="5" />
            <x-ui.textarea wire:model="authorizedTelegramChatIds" label="IDs de chats autorizados" rows="5" />
            <x-ui.input wire:model="defaultExpiresInMinutes" type="number" min="1" label="Expiración predeterminada (minutos)" />
            <x-ui.input wire:model="maxExpiresInMinutes" type="number" min="1" label="Expiración máxima (minutos)" />
            <x-ui.textarea wire:model="allowedAbilities" label="Permisos Sanctum permitidos" rows="5" />
            <x-ui.input wire:model="maxPendingPerUser" type="number" min="1" label="Máximo de solicitudes por usuario" />
            <x-ui.input wire:model="cooldownMinutes" type="number" min="1" label="Espera entre solicitudes (minutos)" />
            <x-ui.input wire:model="approvalTimeoutMinutes" type="number" min="1" label="Tiempo máximo para aprobar (minutos)" />
            <x-ui.input wire:model="webhookUrl" label="URL del webhook de n8n" :error="$errors->first('webhookUrl')" />
            <div class="space-y-3"><x-ui.toggle wire:model="notifyOnApproval" label="Notificar al aprobar" /><x-ui.toggle wire:model="notifyOnRejection" label="Notificar al rechazar" /></div>
            <div class="md:col-span-2 flex flex-wrap gap-2"><x-ui.button type="submit" loading-target="save">Guardar ajustes</x-ui.button><x-ui.button type="button" variant="secondary" wire:click="test" loading-target="test">Probar conexión con n8n</x-ui.button>@if($lastTestStatus !== null)<x-ui.badge :tone="$lastTestStatus >= 200 && $lastTestStatus < 400 ? 'success' : 'warning'">HTTP {{ $lastTestStatus }}</x-ui.badge>@endif</div>
        </form>
    </x-ui.card>
</div>