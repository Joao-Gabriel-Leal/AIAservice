@include('partials.portal-shell', [
    'title' => $title ?? null,
    'subtitle' => $subtitle ?? null,
    'portalMode' => $portalMode ?? 'default',
    'showHeader' => $showHeader ?? true,
    'slot' => $slot,
])
