@props(['message' => null])

@if ($message)
    <p {{ $attributes->merge(['class' => 'mt-1.5 text-sm text-[color:var(--color-danger)]']) }}>
        {{ $message }}
    </p>
@endif
