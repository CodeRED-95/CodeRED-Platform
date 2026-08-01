<x-layouts.public page-title="Solicitar token de acceso">
    <section class="w-full rounded-2xl border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] p-6 shadow-xl md:p-8">
        <div class="mb-6 flex items-center gap-3">
            <x-ui.logo variant="symbol" class="h-11 w-11 rounded-xl" />
            <div>
                <p class="text-xs uppercase tracking-[0.24em] text-[color:var(--color-text-muted)]">CodeRED Platform</p>
                <h1 class="font-display text-2xl font-semibold text-white">Solicitar token de acceso</h1>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-100">
                <p class="font-semibold">{{ session('success') }}</p>
                <p class="mt-1">Código de seguimiento: <span class="font-mono font-semibold">{{ session('tracking_code') }}</span></p>
                <p class="mt-1 text-emerald-100/80">Un administrador revisará la solicitud. El token no se entrega en esta página.</p>
            </div>
        @endif

        <p class="mb-6 text-sm leading-6 text-[color:var(--color-text-secondary)]">Completa este formulario para pedir un token de solo lectura para integraciones autorizadas. No necesitas iniciar sesión y nunca mostraremos el token públicamente.</p>

        <form method="POST" action="{{ route('public.token-requests.store') }}" class="space-y-4">
            @csrf
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <input type="hidden" name="source" value="{{ old('source', $source) }}">
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input name="requester_name" label="Nombre del solicitante" :value="old('requester_name')" required :error="$errors->first('requester_name')" />
                <fieldset class="grid gap-3">
                    <legend class="text-sm font-semibold text-white">Medio de entrega</legend>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach (['whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'email' => 'Correo'] as $value => $label)
                            <label class="flex items-center gap-2 rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-3 py-2 text-sm text-white">
                                <input type="radio" name="delivery_method" value="{{ $value }}" @checked(old('delivery_method', 'whatsapp') === $value) class="accent-[color:var(--color-brand-primary)]">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-ui.form-error :message="$errors->first('delivery_method')" />
                </fieldset>
            </div>
            <x-ui.input name="delivery_destination" label="Número, usuario o correo de entrega" :value="old('delivery_destination')" placeholder="+51987654321, @usuario o correo@dominio.com" required :error="$errors->first('delivery_destination')" />
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input name="installation_name" label="Nombre de instalación o equipo" :value="old('installation_name', $installationName)" required :error="$errors->first('installation_name')" />
                <fieldset class="grid gap-3">
                    <legend class="text-sm font-semibold text-white">Aplicación o integración</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (['shalom-control-search' => 'Buscador Shalom Control', 'other' => 'Otro'] as $value => $label)
                            <label class="flex items-center gap-2 rounded-[var(--radius-control)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-3 py-2 text-sm text-white">
                                <input type="radio" name="integration_type" value="{{ $value }}" @checked(old('integration_type', $integrationType) === $value) class="accent-[color:var(--color-brand-primary)]">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-ui.form-error :message="$errors->first('integration_type')" />
                </fieldset>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input name="extension_version" label="Versión de extensión (opcional)" :value="old('extension_version', $extensionVersion)" :error="$errors->first('extension_version')" />
                <x-ui.input name="installation_uuid" label="Identificador público de instalación (opcional)" :value="old('installation_uuid')" :error="$errors->first('installation_uuid')" />
            </div>
            <x-ui.textarea name="reason" label="Motivo o descripción opcional" :value="old('reason')" rows="4" :error="$errors->first('reason')" />
            <label class="flex gap-3 rounded-xl border border-white/10 bg-white/5 p-3 text-sm text-[color:var(--color-text-secondary)]">
                <input type="checkbox" name="terms" value="1" class="mt-1" @checked(old('terms'))>
                <span>Declaro que usaré el token solo para la integración indicada y que no lo compartiré públicamente.</span>
            </label>
            <x-ui.form-error :message="$errors->first('terms') ?: $errors->first('website')" />
            <x-ui.button type="submit" class="w-full">Enviar solicitud</x-ui.button>
        </form>
    </section>
</x-layouts.public>
