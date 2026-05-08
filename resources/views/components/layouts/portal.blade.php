@props([
    'title' => null,
    'subtitle' => null,
    'showSubtitle' => false,
    'portalMode' => 'default',
    'showHeader' => true,
    'headerVariant' => null,
])

@include('partials.portal-shell', [
    'title' => $title,
    'subtitle' => $subtitle,
    'showSubtitle' => $showSubtitle,
    'portalMode' => $portalMode,
    'showHeader' => $showHeader,
    'headerVariant' => $headerVariant,
    'slot' => $slot,
])
