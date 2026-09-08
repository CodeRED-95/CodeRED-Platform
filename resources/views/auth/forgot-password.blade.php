<x-layouts.auth page-title="{{ $pageTitle }}">
    <form method="POST" action="{{ route('password.email') }}" class="w-full rounded-[var(--radius-modal)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]/95 px-5 py-6 shadow-2xl backdrop-blur sm:px-8 sm:py-8">
        @csrf
        <div class="mb-7 text-center"><x-ui.logo variant="symbol" class="mx-auto mb-4 h-10 w-10" /><h1 class="font-display text-3xl font-semibold tracking-tight">Recuperar contraseña</h1><p class="mt-2 text-sm text-[color:var(--color-text-secondary)]">Te enviaremos un enlace seguro si el correo está registrado.</p></div>
        @if (session('status')) <x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert> @endif
        @if ($errors->any()) <x-ui.alert tone="danger" class="mb-5">{{ $errors->first('email') }}</x-ui.alert> @endif
        <x-ui.input type="email" id="forgot-email" name="email" label="Correo electrónico" autocomplete="email" :value="old('email')" :error="$errors->first('email')" required />
        <x-ui.button type="submit" class="mt-5 w-full py-3.5">Enviar enlace</x-ui.button>
        <x-ui.button href="{{ route('login') }}" variant="outline" class="mt-3 w-full">Volver al inicio de sesión</x-ui.button>
    </form>
</x-layouts.auth>
