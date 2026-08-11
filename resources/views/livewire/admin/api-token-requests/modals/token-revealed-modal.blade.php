@if($revealedToken)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" x-cloak x-data="{
        token: @js($revealedToken),
        copied: false,
        copyToClipboard() {
            navigator.clipboard.writeText(this.token).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            });
        }
    }">
        <div class="w-full max-w-lg rounded-2xl border border-blue-500/30 bg-gradient-to-b from-blue-50 to-white p-8 shadow-2xl dark:from-blue-950/20 dark:to-slate-900">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m7 4a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">Token Revelado</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Copia el token y entrégalo al solicitante de forma segura.</p>
            </div>

            <!-- Warning -->
            <div class="mb-6 rounded-lg border-l-4 border-amber-500 bg-amber-50 p-4 dark:bg-amber-900/20">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                    ⚠️ Este token NO volverá a mostrarse después de cerrar este modal.
                </p>
            </div>

            <!-- Token Display -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-600 dark:text-slate-400">Acceso Seguro</label>
                <div class="relative mt-2">
                    <div class="flex items-center gap-2 rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-inset)] p-4 font-mono text-sm text-emerald-400">
                        <span class="flex-1 break-all" x-text="token"></span>
                        <button @click="copyToClipboard()" class="flex-shrink-0 text-slate-400 hover:text-slate-200">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                    </div>
                    <transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 translate-y-1"
                    >
                        <div x-show="copied" class="absolute right-0 top-full mt-2 flex items-center gap-2 rounded-lg bg-emerald-100 px-3 py-2 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            Copiado!
                        </div>
                    </transition>
                </div>
            </div>

            <!-- Info Box -->
            <div class="mb-6 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                <ul class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                    <li class="flex gap-2">
                        <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span>Copia el token completo con el botón</span>
                    </li>
                    <li class="flex gap-2">
                        <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span>Entrega al solicitante de forma segura (email, llamada, etc.)</span>
                    </li>
                    <li class="flex gap-2">
                        <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span>Confirma la entrega después (debajo)</span>
                    </li>
                </ul>
            </div>

            <!-- Confirm Delivery Section -->
            <div class="mb-6 rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] p-4">
                <h3 class="font-medium text-slate-900 dark:text-white">Confirmar Entrega</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Después de entregar el token, confirma la entrega:</p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Método de Entrega</label>
                        <x-ui.dropdown-select
                            wire:model="deliveryMethod"
                            id="token-delivery-method"
                            label="Método de Entrega"
                            :value="$deliveryMethod"
                            :options="[
                                'presencial' => 'Presencial',
                                'llamada' => 'Llamada Telefónica',
                                'canal_corporativo' => 'Canal Corporativo',
                                'otro' => 'Otro',
                            ]"
                            :error="$errors->first('deliveryMethod')"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Razón (Opcional)</label>
                        <textarea wire:model="deliveryReason" maxlength="500" rows="2" placeholder="Notas sobre la entrega..." class="mt-1 block w-full rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-background-elevated)] px-4 py-3 text-sm text-[color:var(--color-text-primary)] shadow-sm placeholder:text-[color:var(--color-text-muted)] focus:border-[color:var(--color-brand)] focus:outline-none focus:ring-2 focus:ring-[color:var(--color-brand)]/20"></textarea>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="flex justify-end gap-3">
                <button wire:click="$set('revealedToken', null)" class="px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                    Cerrar
                </button>
                <button wire:click="confirmTokenDelivery" class="flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Confirmar Entrega
                </button>
            </div>
        </div>
    </div>
@endif
