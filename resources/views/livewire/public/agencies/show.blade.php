<div class="mx-auto max-w-4xl px-4 py-8">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm text-slate-500">{{ $agency->code }}</p>
        <h1 class="mt-2 text-3xl font-semibold">{{ $agency->name }}</h1>
        <p class="mt-2 text-sm text-slate-500">{{ $agency->department }} / {{ $agency->province }} / {{ $agency->district }}</p>
    </div>

    @if($agency->has_moved)
        <div class="mt-6 rounded-3xl border border-amber-200 bg-amber-50 p-6 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            <strong>Esta agencia se trasladó</strong>
            @if($agency->movedToAgency)
                <p class="mt-2">Esta agencia se trasladó a <a class="underline" href="{{ route('public.agencies.show', $agency->movedToAgency->code) }}">{{ $agency->movedToAgency->name }}</a>.</p>
                <p class="text-sm">{{ $agency->movedToAgency->address }}</p>
            @else
                <p class="mt-2">Esta agencia se trasladó a: {{ $agency->moved_to_address }}.</p>
            @endif
            @if($agency->move_notice)
                <p class="mt-2 text-sm">{{ $agency->move_notice }}</p>
            @endif
        </div>
    @endif

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-semibold">Ubicación</h2>
            <p class="mt-2 text-sm text-slate-500">{{ $agency->address }}</p>
            @if($agency->map_url)
                <a href="{{ $agency->map_url }}" target="_blank" class="mt-4 inline-flex text-sm underline">Abrir en Google Maps</a>
            @endif
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-semibold">Contacto</h2>
            <p class="mt-2 text-sm text-slate-500">Teléfono: {{ $agency->phone ?? '—' }}</p>
            <p class="text-sm text-slate-500">Correo: {{ $agency->email ?? '—' }}</p>
            <p class="text-sm text-slate-500">Centro de Operaciones: {{ $agency->is_operations_center ? 'Sí' : 'No' }}</p>
            <p class="text-sm text-slate-500">Estado: {{ $agency->statusLabel() }}</p>
        </div>
    </div>
</div>
