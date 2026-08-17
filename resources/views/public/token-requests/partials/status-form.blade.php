<div class="space-y-6">
    <form wire:submit.prevent="checkStatus" class="space-y-4">
        <p class="text-sm leading-6 text-[color:var(--color-text-secondary)]">Ingresa el código de seguimiento que recibiste al enviar tu solicitud para ver su estado actual.</p>
        <x-ui.input wire:model.defer="tracking_code_status" name="tracking_code_status" label="Código de seguimiento" placeholder="CR-XXXXXXXXXX" required />
        {{-- El correo ya no se pide: aquí sólo se consulta el ESTADO. Revelar el
             token sigue exigiendo el código de verificación de un solo uso que
             se envía al destino de entrega. --}}
        <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="checkStatus">
            <span wire:loading.remove wire:target="checkStatus">Consultar estado</span>
            <span wire:loading wire:target="checkStatus">Consultando...</span>
        </x-ui.button>
    </form>

    <div wire:loading wire:target="checkStatus" class="text-center text-sm text-[color:var(--color-text-muted)]">
        Buscando solicitud...
    </div>

    @if ($errorMessage)
        <div class="rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-100">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($foundRequest)
        <div class="space-y-4 rounded-xl border border-white/10 bg-white/5 p-6">
            <h3 class="font-semibold text-white">Detalles de la Solicitud</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="text-[color:var(--color-text-muted)]">Código:</div>
                <div class="font-mono font-semibold text-white">{{ $foundRequest->tracking_code }}</div>

                <div class="text-[color:var(--color-text-muted)]">Estado:</div>
                <div class="font-semibold text-white">{{ $foundRequest->status->label() }}</div>

                <div class="text-[color:var(--color-text-muted)]">Solicitud:</div>
                <div class="text-white">{{ $foundRequest->requested_at->format('d/m/Y H:i') }}</div>

                <div class="text-[color:var(--color-text-muted)]">Última actualización:</div>
                <div class="text-white">{{ $foundRequest->updated_at->format('d/m/Y H:i') }}</div>
                
                <div class="text-[color:var(--color-text-muted)]">Aplicación:</div>
                <div class="text-white">{{ $foundRequest->application_name }}</div>
            </div>

            {{-- Lógica de revelación del token --}}
            @if ($revealedToken)
                <div class="space-y-2">
                    <label class="font-semibold text-white">Token de Acceso</label>
                    <div class="rounded-lg bg-black/50 p-4 font-mono text-emerald-400">{{ $revealedToken }}</div>
                    <p class="text-xs text-amber-400">Este token no volverá a mostrarse. Cópialo y guárdalo en un lugar seguro.</p>
                </div>
            @elseif ($foundRequest->status === \App\Enums\ApiTokenRequestStatus::Approved && !$foundRequest->token_revealed_at)
                <div class="border-t border-white/10 pt-4">
                    <p class="text-sm text-emerald-300">¡Tu solicitud fue aprobada!</p>
                    <x-ui.button wire:click="revealToken" class="mt-2 w-full">
                        Mostrar token una sola vez
                    </x-ui.button>
                </div>
            @elseif ($foundRequest->token_revealed_at)
                 <div class="border-t border-white/10 pt-4">
                    <p class="text-sm text-amber-400">El token ya fue entregado y no puede volver a mostrarse.</p>
                </div>
            @endif
        </div>
    @endif
    
    {{-- Modal de confirmación --}}
    @if ($confirmingReveal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" x-cloak>
            <div class="w-full max-w-md rounded-2xl border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-white">Mostrar token</h3>
                <p class="mt-2 text-sm text-[color:var(--color-text-secondary)]">
                    Por seguridad, este token solo se mostrará una vez. Guárdalo en un lugar seguro antes de cerrar esta ventana. Después de confirmar, no podrás volver a consultarlo.
                </p>
                <div class="mt-6 flex justify-end space-x-4">
                    <x-ui.button variant="secondary" wire:click="$set('confirmingReveal', false)">Cancelar</x-ui.button>
                    <x-ui.button wire:click="confirmRevealToken">Mostrar token</x-ui.button>
                </div>
            </div>
        </div>
    @endif

</div>
