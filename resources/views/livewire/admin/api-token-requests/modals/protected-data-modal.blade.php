@if($showingProtectedData && $protectedData)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" x-cloak>
        <div class="w-full max-w-2xl rounded-2xl border border-amber-500/30 bg-gradient-to-b from-amber-50 to-white p-8 shadow-2xl dark:from-amber-950/20 dark:to-slate-900">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                        <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Datos Protegidos</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Información cifrada de la solicitud</p>
                    </div>
                </div>
                <button type="button" wire:click="closeProtectedData" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200" aria-label="Cerrar">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Warning Message -->
            <div class="mb-6 rounded-lg border-l-4 border-amber-500 bg-amber-50 p-4 dark:bg-amber-900/20">
                <div class="flex gap-3">
                    <svg class="h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="font-medium text-amber-800 dark:text-amber-200">Datos Sensibles</p>
                        <p class="text-sm text-amber-700 dark:text-amber-300">Esta información está siendo visualizada y registrada en auditoría.</p>
                    </div>
                </div>
            </div>

            <!-- Protected Data Grid -->
            <div class="mb-6 grid gap-4 sm:grid-cols-2">
                <!-- Requester Name -->
                <div class="rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-4">
                    <label class="block text-sm font-medium text-slate-600 dark:text-slate-400">Nombre del Solicitante</label>
                    <div class="mt-2 break-words rounded-[var(--radius-control)] bg-[color:var(--color-background)] p-2 font-mono text-sm text-[color:var(--color-text-primary)]">
                        {{ $protectedData['requester_name'] ?? 'N/A' }}
                    </div>
                </div>

                <!-- Requester Phone -->
                <div class="rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-4">
                    <label class="block text-sm font-medium text-slate-600 dark:text-slate-400">Teléfono</label>
                    <div class="mt-2 break-words rounded-[var(--radius-control)] bg-[color:var(--color-background)] p-2 font-mono text-sm text-[color:var(--color-text-primary)]">
                        {{ $protectedData['requester_phone'] ?? 'N/A' }}
                    </div>
                </div>

                <!-- Purpose -->
                <div class="rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-4 sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 dark:text-slate-400">Motivo de la Solicitud</label>
                    <div class="mt-2 break-words rounded-[var(--radius-control)] bg-[color:var(--color-background)] p-2 font-mono text-sm text-[color:var(--color-text-primary)]">
                        {{ $protectedData['purpose'] ?? 'N/A' }}
                    </div>
                </div>

                <!-- Delivery Method -->
                <div class="rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-4">
                    <label class="block text-sm font-medium text-slate-600 dark:text-slate-400">Método de Entrega</label>
                    <div class="mt-2 break-words rounded-[var(--radius-control)] bg-[color:var(--color-background)] p-2 font-mono text-sm text-[color:var(--color-text-primary)]">
                        {{ $protectedData['delivery_method'] ?? 'N/A' }}
                    </div>
                </div>

                <!-- Delivery Reason -->
                <div class="rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-4">
                    <label class="block text-sm font-medium text-slate-600 dark:text-slate-400">Razón de Entrega</label>
                    <div class="mt-2 break-words rounded-[var(--radius-control)] bg-[color:var(--color-background)] p-2 font-mono text-sm text-[color:var(--color-text-primary)]">
                        {{ $protectedData['delivery_reason'] ?? 'N/A' }}
                    </div>
                </div>
            </div>

            <!-- Audit Note -->
            <div class="mb-6 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    ✓ Esta acción está siendo registrada en la auditoría con tu usuario, IP, fecha y hora.
                </p>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3">
                <button type="button" wire:click="closeProtectedData" class="px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
@endif
