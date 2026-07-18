@props(['value' => null, 'context' => null])

@if ($context === 'agency-status')
    <x-ui.status-icon :status="$value" {{ $attributes }} />
@elseif ($context === 'user-status')
    @switch($value)
        @case('active')
            <x-ui.status-icon status="active" {{ $attributes }} />
            @break
        @case('inactive')
            <x-ui.status-icon status="inactive" {{ $attributes }} />
            @break
        @case('suspended')
            <svg {{ $attributes->class('text-amber-400') }} viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.75"/>
                <path d="M7 10h6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
            @break
        @default
            <svg {{ $attributes->class('text-slate-400') }} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="10" cy="10" r="3"/></svg>
    @endswitch
@else
    <svg {{ $attributes->class('text-blue-400') }} viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5"/>
        <circle cx="10" cy="10" r="2.25" fill="currentColor"/>
    </svg>
@endif
