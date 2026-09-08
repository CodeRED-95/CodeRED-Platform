<x-layouts.auth page-title="{{ $pageTitle }}">
    <form method="POST" action="{{ route('password.update') }}" class="w-full rounded-[var(--radius-modal)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]/95 px-5 py-6 shadow-2xl backdrop-blur sm:px-8 sm:py-8">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">
        <div class="mb-7 text-center"><x-ui.logo variant="symbol" class="mx-auto mb-4 h-10 w-10" /><h1 class="font-display text-3xl font-semibold tracking-tight">Nueva contraseña</h1><p class="mt-2 text-sm text-[color:var(--color-text-secondary)]">Elige una contraseña segura de al menos 12 caracteres.</p></div>
        @if ($errors->any()) <x-ui.alert tone="danger" class="mb-5">{{ $errors->first() }}</x-ui.alert> @endif
        <div class="space-y-4"><x-ui.input type="password" id="reset-password" name="password" label="Nueva contraseña" autocomplete="new-password" :error="$errors->first('password')" required /><x-ui.input type="password" id="reset-password-confirmation" name="password_confirmation" label="Confirmar contraseña" autocomplete="new-password" :error="$errors->first('password_confirmation')" required /></div>
        <x-ui.button type="submit" class="mt-5 w-full py-3.5">Actualizar contraseña</x-ui.button>
    </form>
</x-layouts.auth>
