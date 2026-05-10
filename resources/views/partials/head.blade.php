<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

@php
    $faviconIcoVersion = file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : time();
    $faviconSvgVersion = file_exists(public_path('favicon.svg')) ? filemtime(public_path('favicon.svg')) : $faviconIcoVersion;
    $appleTouchIconVersion = file_exists(public_path('apple-touch-icon.png')) ? filemtime(public_path('apple-touch-icon.png')) : $faviconIcoVersion;
@endphp

<link rel="icon" href="/favicon.ico?v={{ $faviconIcoVersion }}" sizes="any">
<link rel="icon" href="/favicon.svg?v={{ $faviconSvgVersion }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ $appleTouchIconVersion }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@php($preferredAppearance = auth()->check() ? auth()->user()->preferredTheme() : 'light')

<script>
    window.localStorage.setItem('flux.appearance', @js($preferredAppearance));

    if (window.localStorage.getItem('portal.sidebar.collapsed') === 'true') {
        document.documentElement.classList.add('portal-sidebar-collapsed');
    }
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
