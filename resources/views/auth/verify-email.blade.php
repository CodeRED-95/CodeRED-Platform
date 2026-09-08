<x-layouts.auth page-title="{{ $pageTitle }}">
    <div class="w-full rounded-[var(--radius-modal)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]/95 px-5 py-6 shadow-2xl backdrop-blur sm:px-8 sm:py-8">
        <div class="mb-7 flex flex-col items-center gap-4 text-center">
            <div class="flex size-16 items-center justify-center rounded-[var(--radius-card)] border border-white/10 bg-white/5"><x-ui.logo variant="symbol" class="h-8 w-8" /></div>
            <div class="space-y-2"><h1 class="font-display text-3xl font-semibold tracking-tight">Verifica tu correo</h1><p class="text-sm text-[color:var(--color-text-secondary)]">Hemos enviado un código de 6 dígitos a:</p><p class="font-semibold text-white">{{ $emailMasked }}</p></div>
        </div>
        @if (session('success')) <x-ui.alert tone="success" class="mb-5">{{ session('success') }}</x-ui.alert> @endif
        @if ($errors->any()) <x-ui.alert tone="danger" class="mb-5">{{ $errors->first('code') }}</x-ui.alert> @endif
        <form method="POST" action="{{ route('email.verify.submit') }}">
            @csrf
            <x-ui.input id="email-verification-code" name="code" label="Código de verificación" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" class="text-center text-2xl tracking-[0.45em]" :error="$errors->first('code')" required />
            <x-ui.button type="submit" class="mt-5 w-full py-3.5 text-base font-semibold">Verificar código</x-ui.button>
        </form>
        <div class="mt-6 text-center text-sm text-[color:var(--color-text-secondary)]">
            <p>El código expira en {{ ceil(($status['expires_in'] ?? 0) / 60) }} minutos.</p>
            @if (($resendAvailableIn ?? $status['resend_available_in'] ?? 0) > 0)
                <p class="mt-2" data-email-verification-countdown="{{ (int) ($resendAvailableIn ?? $status['resend_available_in']) }}">Reenviar código en <span data-countdown-display>{{ gmdate('i:s', (int) ($resendAvailableIn ?? $status['resend_available_in'])) }}</span></p>
            @else
                <form method="POST" action="{{ route('email.verify.resend') }}" class="mt-2">@csrf<button type="submit" class="font-semibold text-[color:var(--color-brand-light)] hover:underline">Reenviar código</button></form>
            @endif
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">@csrf<button class="text-xs text-[color:var(--color-text-muted)] hover:text-white">Cerrar sesión</button></form>
    </div>
</x-layouts.auth>
