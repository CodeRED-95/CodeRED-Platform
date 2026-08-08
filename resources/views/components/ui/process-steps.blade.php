@props(['steps' => []])

{{-- steps: array de ['label' => string, 'status' => completed|active|pending|failed, 'description' => opcional] --}}

<ol {{ $attributes->class('space-y-2.5') }}>
    @foreach ($steps as $step)
        @php
            $status = $step['status'] ?? 'pending';
            $styles = [
                'completed' => ['icon' => 'check', 'badge' => 'bg-emerald-500/10 text-[color:var(--color-success)]', 'text' => 'text-[color:var(--color-text-primary)]'],
                'active' => ['icon' => null, 'badge' => 'bg-[color:var(--color-brand-soft)] text-[color:var(--color-brand-light)]', 'text' => 'text-[color:var(--color-text-primary)] font-medium'],
                'pending' => ['icon' => null, 'badge' => 'bg-white/5 text-[color:var(--color-text-muted)]', 'text' => 'text-[color:var(--color-text-secondary)]'],
                'failed' => ['icon' => 'x', 'badge' => 'bg-rose-500/10 text-[color:var(--color-danger)]', 'text' => 'text-[color:var(--color-danger)] font-medium'],
            ];
            $style = $styles[$status] ?? $styles['pending'];
            $statusLabel = ['completed' => 'completado', 'active' => 'en curso', 'failed' => 'falló', 'pending' => 'pendiente'][$status] ?? 'pendiente';
        @endphp
        <li class="flex items-start gap-3">
            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full {{ $style['badge'] }}" aria-hidden="true">
                @if ($style['icon'])
                    <x-ui.icon :name="$style['icon']" class="size-3" />
                @elseif ($status === 'active')
                    <span class="size-2 rounded-full bg-current motion-safe:animate-pulse"></span>
                @else
                    <span class="size-1.5 rounded-full bg-current"></span>
                @endif
            </span>
            <div class="min-w-0">
                <p class="text-sm {{ $style['text'] }}">
                    {{ $step['label'] }}
                    <span class="sr-only">({{ $statusLabel }})</span>
                </p>
                @isset($step['description'])
                    <p class="text-xs text-[color:var(--color-text-secondary)]">{{ $step['description'] }}</p>
                @endisset
            </div>
        </li>
    @endforeach
</ol>
