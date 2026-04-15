@php($gradientId = 'brand-lockup-gradient-'.uniqid())

<svg viewBox="0 0 820 430" fill="none" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $gradientId }}" x1="174" y1="22" x2="346" y2="329" gradientUnits="userSpaceOnUse">
            <stop stop-color="#53E8E6" />
            <stop offset="1" stop-color="#29BDE0" />
        </linearGradient>
    </defs>

    <rect x="140" y="34" width="178" height="312" rx="89" fill="url(#{{ $gradientId }})" />
    <circle cx="229" cy="115" r="57" fill="white" />
    <circle cx="229" cy="258" r="10.5" fill="#2D38D2" />

    <text x="78" y="348" fill="#2D38D2" font-size="120" font-weight="700" letter-spacing="-7" font-family="Instrument Sans, Arial, sans-serif">aia</text>
    <text x="286" y="348" fill="#40D7D8" font-size="120" font-weight="700" letter-spacing="-7" font-family="Instrument Sans, Arial, sans-serif">tech</text>
</svg>
