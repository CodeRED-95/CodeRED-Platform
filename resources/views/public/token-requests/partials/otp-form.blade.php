<div class="space-y-6">
    <div class="rounded-xl border border-blue-500/20 bg-blue-50 p-4 dark:border-blue-500/30 dark:bg-blue-950/20">
        <div class="flex gap-3">
            <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11-1a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd" />
            </svg>
            <div>
                <h3 class="font-semibold text-blue-900 dark:text-blue-100">Verificación de Seguridad</h3>
                <p class="mt-1 text-sm text-blue-700 dark:text-blue-200">
                    Hemos enviado un código de 6 dígitos a {{ $emailMasked }} para verificar tu identidad.
                </p>
            </div>
        </div>
    </div>

    <form wire:submit.prevent="verifyOtp" class="space-y-4">
        <!-- OTP Code Input -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Código OTP (6 dígitos)</label>
            <input
                type="text"
                inputmode="numeric"
                maxlength="6"
                pattern="[0-9]{6}"
                placeholder="000000"
                wire:model.defer="otpCode"
                class="mt-2 block w-full rounded-lg border-gray-300 text-center text-2xl tracking-widest shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white font-mono"
            />
            @error('otpCode')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Status Messages -->
        @if($otpErrorMessage)
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/30 dark:bg-red-950/20">
                <p class="text-sm text-red-700 dark:text-red-400">{{ $otpErrorMessage }}</p>
            </div>
        @endif

        <!-- Attempts and Resends Info -->
        <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400">
            <div>
                <span class="font-medium">{{ $otpAttemptsRemaining }}</span>
                intentos restantes
            </div>
            <div>
                Vence en {{ $otpExpiresAt ? \Carbon\Carbon::parse($otpExpiresAt)->diffInMinutes(now()) : 10 }} minutos
            </div>
        </div>

        <!-- Submit Button -->
        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:bg-gray-400 dark:bg-blue-700 dark:hover:bg-blue-600"
        >
            <span wire:loading.remove>Verificar Código</span>
            <span wire:loading>Verificando...</span>
        </button>

        <!-- Resend Code Link -->
        @if($otpResendsRemaining > 0)
            <button
                type="button"
                wire:click="requestOtp"
                class="w-full text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
            >
                ¿No recibiste el código? Reenviar ({{ $otpResendsRemaining }} reenvíos restantes)
            </button>
        @else
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">Máximo de reenvíos alcanzado</p>
                <p class="text-xs text-gray-500 dark:text-gray-500">Contacta con soporte si tienes problemas</p>
            </div>
        @endif
    </form>

    <!-- Security Info -->
    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800/50">
        <p class="text-xs text-gray-600 dark:text-gray-400">
            🔒 Tu código está protegido. Nunca compartimos información personal. Máximo 5 intentos, máximo 3 reenvíos.
        </p>
    </div>
</div>
