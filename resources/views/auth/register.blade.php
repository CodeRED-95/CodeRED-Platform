<x-layouts.auth page-title="{{ $pageTitle ?? 'Crear cuenta' }}">
    <x-slot:promo>
        <div class="relative mx-auto w-full max-w-[clamp(18rem,24vw,29rem)]">
            <div class="pointer-events-none absolute inset-0 -z-10 translate-y-2 rounded-full bg-[radial-gradient(circle_at_center,rgba(225,29,72,0.34),rgba(225,29,72,0)_65%)] blur-2xl"></div>
            <x-ui.logo variant="full" class="w-full h-auto drop-shadow-[0_0_30px_rgba(225,29,72,0.18)]" />
        </div>

        <div class="mt-6 space-y-2.5 xl:mt-7 xl:space-y-3">
            <p class="text-sm uppercase tracking-[0.32em] text-[color:var(--color-brand-light)]">CodeRED Platform</p>
            <h1 class="font-display text-[clamp(2.4rem,2.8vw,3.55rem)] font-semibold leading-[0.96] tracking-tight">Crea tu cuenta de acceso</h1>
            <p class="max-w-xl text-[clamp(1rem,1.05vw,1.125rem)] leading-[1.55] text-[color:var(--color-text-secondary)]">
                El registro público asigna automáticamente el rol <strong>viewer</strong> para que puedas consultar agencias y sincronizar tu extensión sin permisos administrativos.
            </p>
        </div>

        <div class="grid max-w-2xl gap-3 pt-5 xl:gap-4 xl:pt-6">
            <div class="flex items-start gap-3 rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-white/3 p-3.5 shadow-[var(--shadow-card)] backdrop-blur xl:gap-4 xl:p-4">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-light)] xl:size-12">
                    <x-ui.icon name="info" class="size-5" />
                </div>
                <div>
                    <p class="text-[clamp(0.98rem,1vw,1.05rem)] font-semibold text-white">Acceso de consulta</p>
                    <p class="mt-0.5 text-sm leading-6 text-[color:var(--color-text-secondary)]">Consulta agencias y tu información sincronizada sin permisos administrativos.</p>
                </div>
            </div>

            <div class="flex items-start gap-3 rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-white/3 p-3.5 shadow-[var(--shadow-card)] backdrop-blur xl:gap-4 xl:p-4">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-light)] xl:size-12">
                    <x-ui.icon name="shield" class="size-5" />
                </div>
                <div>
                    <p class="text-[clamp(0.98rem,1vw,1.05rem)] font-semibold text-white">Sesión segura</p>
                    <p class="mt-0.5 text-sm leading-6 text-[color:var(--color-text-secondary)]">Tu cuenta se crea con acceso protegido y validación backend.</p>
                </div>
            </div>

            <div class="flex items-start gap-3 rounded-[var(--radius-card)] border border-[color:var(--color-border)] bg-white/3 p-3.5 shadow-[var(--shadow-card)] backdrop-blur xl:gap-4 xl:p-4">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-light)] xl:size-12">
                    <x-ui.icon name="refresh" class="size-5" />
                </div>
                <div>
                    <p class="text-[clamp(0.98rem,1vw,1.05rem)] font-semibold text-white">Sincronización propia</p>
                    <p class="mt-0.5 text-sm leading-6 text-[color:var(--color-text-secondary)]">Conecta Shalom Recordar con tu instalación y tu usuario.</p>
                </div>
            </div>
        </div>

        <footer class="pt-6 text-sm text-[color:var(--color-text-secondary)] xl:pt-7">
            © 2026 CodeRED Platform. Todos los derechos reservados.
        </footer>
    </x-slot:promo>

    <form
        method="POST"
        action="{{ route('register.store') }}"
        class="w-full rounded-[var(--radius-modal)] border border-[color:var(--color-border-subtle)] bg-[color:var(--color-background-elevated)]/95 px-5 py-6 shadow-2xl backdrop-blur sm:px-6 sm:py-7 lg:px-8 lg:py-7"
    >
        @csrf
        <div class="mb-6 flex flex-col items-center gap-4 text-center lg:mb-6">
            <div class="flex size-16 items-center justify-center rounded-[var(--radius-card)] border border-white/10 bg-white/5 shadow-[0_0_0_1px_rgba(255,255,255,0.03)]">
                <x-ui.logo variant="symbol" class="h-8 w-8" />
            </div>
            <div class="space-y-2">
                <h2 class="font-display text-3xl font-semibold tracking-tight">Crear cuenta</h2>
                <p class="text-sm text-[color:var(--color-text-secondary)]">Tu cuenta se creará con acceso de consulta y sincronización propia.</p>
            </div>
        </div>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-6 text-sm">
                <p class="font-medium text-[color:var(--color-danger)]">Revisa los campos marcados.</p>
            </x-ui.alert>
        @endif

        <div class="space-y-4">
            <x-ui.input type="text" id="name" name="name" label="Nombre" autocomplete="name" :value="old('name')" :error="$errors->first('name')" />
            <x-ui.input type="email" id="email" name="email" label="Correo electrónico" autocomplete="email" :value="old('email')" :error="$errors->first('email')" />
            <x-ui.input type="password" id="password" name="password" label="Contraseña" autocomplete="new-password" :error="$errors->first('password')" />
            <x-ui.input type="password" id="password_confirmation" name="password_confirmation" label="Confirmar contraseña" autocomplete="new-password" :error="$errors->first('password_confirmation')" />
        </div>

        <p class="mt-4 text-xs text-[color:var(--color-text-secondary)]">Al registrarte aceptas usar tu cuenta solo para consulta y sincronización propia.</p>

        <div class="mt-6 flex flex-col gap-3">
            <x-ui.button type="submit" variant="primary" class="w-full">Crear cuenta</x-ui.button>
            <x-ui.button href="{{ route('login') }}" variant="outline" class="w-full">Ya tengo cuenta</x-ui.button>
        </div>

        <p class="mt-5 text-center text-sm text-[color:var(--color-text-secondary)] lg:hidden">
            Plataforma modular • Segura • Confiable
        </p>
    </form>
</x-layouts.auth>
