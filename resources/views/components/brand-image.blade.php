@props([
    'alt' => config('app.name', 'AIA Service'),
])

<x-brand-mark :alt="$alt" {{ $attributes }} />
