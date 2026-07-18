@props(['status'])

@switch($status)
    @case('active')
        <svg {{ $attributes->class('text-emerald-400') }} viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.75"/>
            <path d="m6.75 10 2.1 2.1 4.4-4.4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break
    @case('inactive')
        <svg {{ $attributes->class('text-slate-400') }} viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.75"/>
            <path d="m6.5 13.5 7-7" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
        </svg>
        @break
    @case('temporarily_closed')
        <svg {{ $attributes->class('text-amber-400') }} viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.75"/>
            <path d="M10 5.75V10l2.75 1.75" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break
    @case('under_review')
        <svg {{ $attributes->class('text-blue-400') }} viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="8.75" cy="8.75" r="4.75" stroke="currentColor" stroke-width="1.75"/>
            <path d="m12.25 12.25 3.25 3.25" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
        </svg>
        @break
    @case('moved')
        <svg {{ $attributes->class('text-violet-400') }} viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M4 7h11m0 0-3-3m3 3-3 3M16 13H5m0 0 3 3m-3-3 3-3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break
@endswitch
