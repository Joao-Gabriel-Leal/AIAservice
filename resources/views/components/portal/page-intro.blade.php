@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'showDescription' => true,
    'variant' => 'editorial',
])

@php
    $variant = in_array($variant, ['editorial', 'detail', 'compact'], true) ? $variant : 'editorial';
    $hasBody = trim((string) $slot) !== '';
@endphp

<section {{ $attributes->class(['portal-page-intro', 'portal-page-intro--'.$variant]) }}>
    <div class="portal-page-intro-main">
        <div class="portal-page-intro-copy">
            @if ($eyebrow)
                <p class="portal-page-intro-eyebrow">{{ $eyebrow }}</p>
            @endif

            @if ($title)
                <h1 class="portal-page-intro-title">{{ $title }}</h1>
            @endif

            @if ($showDescription && $description)
                <p class="portal-page-intro-description">{{ $description }}</p>
            @endif
        </div>

        @if (isset($actions))
            <div class="portal-page-intro-actions">
                {{ $actions }}
            </div>
        @endif
    </div>

    @if (isset($meta) || $hasBody)
        <div class="portal-page-intro-support">
            @if (isset($meta))
                <div class="portal-page-intro-meta">
                    {{ $meta }}
                </div>
            @endif

            @if ($hasBody)
                <div class="portal-page-intro-body">
                    {{ $slot }}
                </div>
            @endif
        </div>
    @endif
</section>
