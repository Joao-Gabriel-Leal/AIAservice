@props([
    'alt' => config('app.name', 'AIA Service'),
])

@php
    $svgId = str_replace('.', '', uniqid('aia-brand-', true));
    $bgId = $svgId.'-bg';
    $accentId = $svgId.'-accent';
    $glowId = $svgId.'-glow';
@endphp

<span {{ $attributes->class('inline-flex items-center justify-center') }} role="img" aria-label="{{ $alt }}">
    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" class="h-full w-full">
        <defs>
            <linearGradient id="{{ $bgId }}" x1="10" y1="8" x2="54" y2="56" gradientUnits="userSpaceOnUse">
                <stop stop-color="#172453" />
                <stop offset="0.54" stop-color="#293D9E" />
                <stop offset="1" stop-color="#314BE8" />
            </linearGradient>
            <linearGradient id="{{ $accentId }}" x1="19" y1="18" x2="48" y2="48" gradientUnits="userSpaceOnUse">
                <stop stop-color="#8CF8F1" />
                <stop offset="0.48" stop-color="#4EE7DC" />
                <stop offset="1" stop-color="#16C2D4" />
            </linearGradient>
            <radialGradient id="{{ $glowId }}" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(47 17) rotate(90) scale(18)">
                <stop stop-color="#8DB5FF" stop-opacity="0.82" />
                <stop offset="1" stop-color="#8DB5FF" stop-opacity="0" />
            </radialGradient>
        </defs>

        <rect x="4" y="4" width="56" height="56" rx="18" fill="url(#{{ $bgId }})" />
        <rect x="5.5" y="5.5" width="53" height="53" rx="16.5" stroke="#FFFFFF" stroke-opacity="0.16" stroke-width="1.5" />
        <circle cx="46.5" cy="17.5" r="16" fill="url(#{{ $glowId }})" opacity="0.46" />
        <path d="M16.2 43.8L22.5 20.9C22.82 19.75 23.87 18.95 25.05 18.95H26.25C27.42 18.95 28.47 19.74 28.8 20.88L35.25 43.8H30.45L29.18 38.72H22.22L20.95 43.8H16.2Z" fill="url(#{{ $accentId }})" />
        <path d="M23.52 32.9H27.85L25.67 24.42L23.52 32.9Z" fill="#10215D" />
        <rect x="30.15" y="18.95" width="4.35" height="24.85" rx="2.175" fill="url(#{{ $accentId }})" />
        <path d="M36.7 43.8L43 20.9C43.32 19.75 44.37 18.95 45.55 18.95H46.75C47.92 18.95 48.97 19.74 49.3 20.88L55.75 43.8H50.95L49.68 38.72H42.72L41.45 43.8H36.7Z" fill="url(#{{ $accentId }})" />
        <path d="M44.02 32.9H48.35L46.17 24.42L44.02 32.9Z" fill="#10215D" />
    </svg>
</span>
