@props([
    'title' => null,
    'description' => null,
    'compact' => false,
])

@php
    $surfaceClasses = $compact ? 'portal-filter-bar portal-filter-bar--compact' : 'portal-filter-bar';
    $hasBody = trim((string) $slot) !== '';
@endphp

<section {{ $attributes->class($surfaceClasses) }}>
    @if ($title || $description || isset($actions))
        <div class="portal-filter-bar-header">
            <div>
                @if ($title)
                    <p class="portal-filter-bar-title">{{ $title }}</p>
                @endif

                @if ($description)
                    <p class="portal-filter-bar-description">{{ $description }}</p>
                @endif
            </div>

            @if (isset($actions))
                <div class="portal-filter-bar-actions">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    @if ($hasBody)
        <div class="portal-filter-bar-content">
            {{ $slot }}
        </div>
    @endif
</section>
