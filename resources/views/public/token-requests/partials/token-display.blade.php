<div class="space-y-6">
    <div class="rounded-xl border border-emerald-500/20 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-950/20">
        <div class="flex gap-3">
            <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <div>
                <h3 class="font-semibold text-emerald-900 dark:text-emerald-100">¡Solicitud Aprobada!</h3>
                <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-200">
                    Tu solicitud de token ha sido aprobada. Aquí está tu acceso seguro.
                </p>
            </div>
        </div>
    </div>

    @if($revealedToken)
        <!-- Token Display -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Token de Acceso</label>
            <div class="relative">
                <div x-data="{ copied: false, token: @js($revealedToken) }" class="flex items-center gap-2 rounded-lg border border-gray-300 bg-gray-900 p-4 font-mono text-sm text-emerald-400 dark:border-gray-600 dark:bg-gray-950">
                    <span class="flex-1 break-all select-all" x-text="token"></span>
                    <button
                        type="button"
                        @click="navigator.clipboard.writeText(token); copied = true; setTimeout(() => copied = false, 2000)"
                        class="flex-shrink-0 text-gray-400 hover:text-gray-200"
                    >
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
                    <div x-data="{ copied: false }" x-show="copied" class="absolute right-0 top-full mt-2 flex items-center gap-2 rounded-lg bg-emerald-100 px-3 py-2 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                        ¡Copiado!
                    </div>
                </transition>
            </div>
            <p class="text-xs text-amber-600 dark:text-amber-400">
                ⚠️ Este token NO volverá a mostrarse. Cópialo y guárdalo en un lugar seguro.
            </p>
        </div>

        <!-- Important Info -->
        <div class="space-y-3 rounded-lg bg-blue-50 p-4 dark:bg-blue-950/20">
            <div class="flex gap-2">
                <svg class="h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11-1a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd" />
                </svg>
                <div class="text-sm text-blue-700 dark:text-blue-300">
                    <p class="font-medium">Próximos pasos:</p>
                    <ol class="mt-1 space-y-1 pl-4 list-decimal text-xs">
                        <li>Copia el token usando el botón</li>
                        <li>Guárdalo en un lugar seguro</li>
                        <li>Úsalo en tu aplicación o integración</li>
                        <li>Confirma que lo recibiste (abajo)</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Confirm Receipt -->
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/30 dark:bg-emerald-950/20">
            <p class="text-sm font-medium text-emerald-900 dark:text-emerald-100 mb-3">
                ✓ Confirmar Recepción
            </p>
            <button
                type="button"
                wire:click="confirmTokenDelivery"
                class="w-full flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Confirmar que Copié el Token
            </button>
        </div>
    @else
        <!-- Request Token Button -->
        <button
            type="button"
            wire:click="revealToken"
            class="w-full flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-medium text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Mostrar Token (Una sola vez)
        </button>
    @endif

    <!-- Security Info Footer -->
    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800/50">
        <p class="text-xs text-gray-600 dark:text-gray-400">
            🔒 Tu token está protegido mediante cifrado AES-256. Nunca lo mostraremos de nuevo. Guárdalo en un lugar seguro.
        </p>
    </div>
</div>
