@include('partials.portal-shell', [
    'title' => $title ?? null,
    'subtitle' => $subtitle ?? null,
    'showSubtitle' => $showSubtitle ?? false,
    'portalMode' => $portalMode ?? 'default',
    'showHeader' => $showHeader ?? true,
    'headerVariant' => $headerVariant ?? null,
    'slot' => $slot,
])
