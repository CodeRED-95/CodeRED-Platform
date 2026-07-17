<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="rounded-3xl border border-white/10 bg-white/80 p-6 shadow-xl backdrop-blur dark:bg-slate-900/80">
        <h1 class="text-3xl font-semibold">Agencias Shalom</h1>
        <p class="mt-2 text-sm text-slate-500">Consulta pública de agencias activas.</p>
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar por código, nombre o ubicación" class="mt-6 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($agencies as $agency)
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold">{{ $agency->name }}</h2>
                        <p class="text-sm text-slate-500">{{ $agency->code }} · {{ $agency->district }}</p>
                    </div>
                    <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-600">Activa</span>
                </div>
                <p class="mt-4 text-sm text-slate-500">{{ $agency->address }}</p>
            </article>
        @empty
            <div class="rounded-3xl border border-slate-200 bg-white p-8 text-slate-500 dark:border-slate-800 dark:bg-slate-900">No se encontraron agencias.</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $agencies->links() }}</div>
</div>
