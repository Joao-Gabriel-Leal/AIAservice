@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'badge' => null,
    'compact' => false,
])

@php
    $surfaceClasses = $compact ? 'portal-section-hero portal-section-hero--compact' : 'portal-section-hero';
    $hasContent = trim((string) $slot) !== '';
@endphp

<section {{ $attributes->class($surfaceClasses) }}>
    <div class="portal-section-hero-band">
        <div class="max-w-3xl">
            @if ($eyebrow)
                <p class="portal-section-hero-kicker">{{ $eyebrow }}</p>
            @endif

            @if ($title)
                <h2 class="portal-section-hero-title">{{ $title }}</h2>
            @endif

            @if ($description)
                <p class="portal-section-hero-description">{{ $description }}</p>
            @endif
        </div>

        @if (isset($actions) || $badge)
            <div class="portal-section-hero-actions">
                @if ($badge)
                    <span class="portal-hero-badge">{{ $badge }}</span>
                @endif

                {{ $actions ?? '' }}
            </div>
        @endif
    </div>

    @if ($hasContent)
        <div class="portal-section-hero-content">
            {{ $slot }}
        </div>
    @endif
</section>
