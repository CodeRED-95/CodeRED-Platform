<div x-data="{ tab: @entangle('tab') }">
    <section class="w-full rounded-2xl border border-[color:var(--color-border-subtle)] bg-[color:var(--color-surface)] p-6 shadow-xl md:p-8">
        <div class="mb-6 flex items-center gap-3">
            <x-ui.logo variant="symbol" class="h-11 w-11 rounded-xl" />
            <div>
                <p class="text-xs uppercase tracking-[0.24em] text-[color:var(--color-text-muted)]">CodeRED Platform</p>
                <h1 class="font-display text-2xl font-semibold text-white">Solicitud de token de acceso</h1>
            </div>
        </div>

        <div class="mb-6 border-b border-white/10">
            <nav class="-mb-px flex space-x-6">
                <button @click="tab = 'create'" :class="tab === 'create' ? 'border-brand-primary text-brand-primary' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Solicitar token
                </button>
                <button @click="tab = 'status'" :class="tab === 'status' ? 'border-brand-primary text-brand-primary' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Ver estado
                </button>
            </nav>
        </div>

        <div x-show="tab === 'create'">
            @if (session('success') && session('tracking_code'))
                <div class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-100">
                    <p class="font-semibold">{{ session('success') }}</p>
                    <p class="mt-1">Guarda tu código de seguimiento: <span class="font-mono font-semibold">{{ session('tracking_code') }}</span></p>
                    <p class="mt-1 text-emerald-100/80">Podrás usarlo en la pestaña "Ver estado" para consultar el progreso.</p>
                </div>
            @endif

            <p class="mb-6 text-sm leading-6 text-[color:var(--color-text-secondary)]">Completa este formulario para pedir un token de solo lectura para integraciones autorizadas. No necesitas iniciar sesión y nunca mostraremos el token públicamente.</p>
            
            {{-- El formulario de creación original va aquí --}}
            @include('public.token-requests.partials.create-form')
        </div>

        <div x-show="tab === 'status'" style="display: none;">
            {{-- El nuevo formulario de consulta y los resultados van aquí --}}
            @include('public.token-requests.partials.status-form')
        </div>
    </section>
</div>
