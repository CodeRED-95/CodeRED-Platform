<div class="space-y-8">
    <x-ui.page-header
        :title="$agency->name"
        :subtitle="$agency->department.' / '.$agency->province.' / '.$agency->district"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('public.agencies.index') }}" variant="outline">Volver al listado</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if($agency->has_moved)
        <x-ui.alert tone="warning">
            <div class="font-semibold">Esta agencia se trasladó.</div>
            @if($agency->movedToAgency)
                <p class="mt-2">Esta agencia se trasladó a <a class="underline" href="{{ route('public.agencies.show', $agency->movedToAgency->code) }}">{{ $agency->movedToAgency->name }}</a>.</p>
                <p class="text-sm">{{ $agency->movedToAgency->address }}</p>
            @else
                <p class="mt-2">Esta agencia se trasladó a: {{ $agency->moved_to_address }}.</p>
            @endif
            @if($agency->move_notice)
                <p class="mt-2 text-sm">{{ $agency->move_notice }}</p>
            @endif
        </x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header title="Ubicación" />
            <div class="mt-5 space-y-3 text-sm">
                <p>{{ $agency->address }}</p>
                @if($agency->reference)
                    <p class="text-[color:var(--color-text-secondary)]">{{ $agency->reference }}</p>
                @endif
                <x-ui.map-preview class="mt-4" :latitude="$agency->latitude" :longitude="$agency->longitude" :name="$agency->name" :location="$agency->department.' / '.$agency->province.' / '.$agency->district" :label="'Ubicación de '.$agency->name" />
                @if ($agency->latitude && $agency->longitude)
                    <x-ui.button href="{{ 'https://www.google.com/maps/search/?api=1&query='.$agency->latitude.','.$agency->longitude }}" target="_blank" rel="noopener noreferrer" variant="outline">Abrir en Google Maps</x-ui.button>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header title="Contacto y estado" />
            <div class="mt-5 space-y-3 text-sm">
                <p>Teléfono: {{ $agency->phone ?? '—' }}</p>
                <p>Correo: {{ $agency->email ?? '—' }}</p>
                <p>Horario: {{ $agency->schedule ?? '—' }}</p>
                <p>Centro de Operaciones: {{ $agency->is_operations_center ? 'Sí' : 'No' }}</p>
                <p>Estado: {{ $agency->statusLabel() }}</p>
            </div>
        </x-ui.card>
    </div>
</div>
