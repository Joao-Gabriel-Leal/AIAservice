@props([
    'title' => null,
    'subtitle' => null,
    'portalMode' => 'default',
    'showHeader' => true,
])

@include('partials.portal-shell', [
    'title' => $title,
    'subtitle' => $subtitle,
    'portalMode' => $portalMode,
    'showHeader' => $showHeader,
    'slot' => $slot,
])
