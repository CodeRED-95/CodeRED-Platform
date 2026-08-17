<x-ui.alert tone="warning" title="Copia este token ahora. No podrá volver a mostrarse.">
    <div class="mt-3 space-y-3" x-data="codeRedTokenCopy(@js($plainTextToken))">
        <p class="text-sm">Token: <strong>{{ $createdTokenName }}</strong></p>
        <div class="flex flex-col gap-3 sm:flex-row">
            <code
                x-ref="tokenText"
                tabindex="0"
                class="min-w-0 flex-1 overflow-x-auto rounded-xl bg-black/30 px-4 py-3 text-sm text-white focus-ring"
                data-testid="plain-api-token"
            >{{ $plainTextToken }}</code>
            <x-ui.button variant="primary" x-on:click="copy" x-bind:disabled="copying" aria-label="Copiar token recién creado">
                <svg x-show="! copied" class="h-4 w-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-2M6 7h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z" /></svg>
                <svg x-cloak x-show="copied" class="h-4 w-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6" /></svg>
                <span x-text="copied ? 'Copiado ✓' : 'Copiar token'">Copiar token</span>
            </x-ui.button>
            <x-ui.button variant="secondary" x-on:click="select">Seleccionar</x-ui.button>
        </div>
        <x-ui.button variant="ghost" wire:click="dismissPlainToken">Ya lo guardé; ocultar token</x-ui.button>
    </div>
</x-ui.alert>
