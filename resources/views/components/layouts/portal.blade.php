@props([
    'title' => null,
    'subtitle' => null,
    'portalMode' => 'default',
    'showHeader' => true,
    'headerVariant' => null,
])

@include('partials.portal-shell', [
    'title' => $title,
    'subtitle' => $subtitle,
    'portalMode' => $portalMode,
    'showHeader' => $showHeader,
    'headerVariant' => $headerVariant,
    'slot' => $slot,
])
