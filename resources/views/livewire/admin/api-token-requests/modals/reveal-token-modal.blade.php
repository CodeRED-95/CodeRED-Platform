@if($confirmingTokenReveal && $selectedId)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" x-cloak>
        <div class="w-full max-w-lg rounded-2xl border border-emerald-500/30 bg-gradient-to-b from-emerald-50 to-white p-8 shadow-2xl">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-100">
                    <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-[color:var(--color-text-primary)]">Revelar Token</h2>
                <p class="mt-1 text-sm text-[color:var(--color-text-secondary)]">Este token se mostrará UNA sola vez y será registrado en auditoría.</p>
            </div>

            <!-- Warning -->
            <div class="mb-6 rounded-lg border-l-4 border-red-500 bg-red-50 p-4">
                <div class="flex gap-3">
                    <svg class="h-5 w-5 flex-shrink-0 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="font-medium text-red-800">Una sola revelación</p>
                        <p class="text-sm text-red-700">Después de esto, el token no podrá volver a mostrarse.</p>
                    </div>
                </div>
            </div>

            <!-- Confirmation Text -->
            <p class="mb-6 text-sm text-[color:var(--color-text-secondary)]">
                ¿Deseas revelar el token ahora? Esto marcará el token como revelado y registrará la acción en auditoría.
            </p>

            <!-- Footer Actions -->
            <div class="flex justify-end gap-3">
                <button type="button" wire:click="$set('confirmingTokenReveal', false)" class="px-4 py-2 text-sm font-medium text-[color:var(--color-text-secondary)] hover:bg-white/5">
                    Cancelar
                </button>
                <button type="button" wire:click="revealToken" class="flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    Revelar Token
                </button>
            </div>
        </div>
    </div>
@endif
