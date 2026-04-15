@php($gradientId = 'brand-symbol-gradient-'.uniqid())
@php($highlightId = 'brand-symbol-highlight-'.uniqid())
@php($shadowId = 'brand-symbol-shadow-'.uniqid())

<svg viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $gradientId }}" x1="28" y1="10" x2="70" y2="82" gradientUnits="userSpaceOnUse">
            <stop stop-color="#3FD6E0" />
            <stop offset="0.52" stop-color="#28BAD9" />
            <stop offset="1" stop-color="#197CC3" />
        </linearGradient>
        <linearGradient id="{{ $highlightId }}" x1="33" y1="15" x2="48" y2="39" gradientUnits="userSpaceOnUse">
            <stop stop-color="white" stop-opacity="0.36" />
            <stop offset="1" stop-color="white" stop-opacity="0" />
        </linearGradient>
        <filter id="{{ $shadowId }}" x="16" y="4" width="64" height="88" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
            <feDropShadow dx="0" dy="10" stdDeviation="8" flood-color="#09176A" flood-opacity="0.22" />
        </filter>
    </defs>

    <g filter="url(#{{ $shadowId }})">
        <rect x="29" y="10" width="38" height="76" rx="19" fill="url(#{{ $gradientId }})" />
        <rect x="29.75" y="10.75" width="36.5" height="74.5" rx="18.25" stroke="white" stroke-opacity="0.18" stroke-width="1.5" />
        <path d="M38 16C38 13.7909 39.7909 12 42 12H54C56.2091 12 58 13.7909 58 16V39H38V16Z" fill="url(#{{ $highlightId }})" />
    </g>

    <circle cx="48" cy="31" r="13.5" fill="white" />
    <circle cx="48" cy="59" r="4.2" fill="#2331A8" />
</svg>
