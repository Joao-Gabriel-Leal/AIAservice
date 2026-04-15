@props([
    'alt' => config('app.name', 'AIA Service'),
])

<img
    src="{{ asset('branding/aia-tech-logo.jpeg') }}"
    alt="{{ $alt }}"
    {{ $attributes->class('block h-auto w-full object-contain') }}
/>
