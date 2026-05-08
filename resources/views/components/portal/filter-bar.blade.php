@props([
    'title' => null,
    'description' => null,
    'showDescription' => true,
    'compact' => false,
    'collapsible' => false,
    'persistKey' => null,
    'defaultCollapsed' => false,
])

@php
    $surfaceClasses = collect([
        'portal-filter-bar',
        $compact ? 'portal-filter-bar--compact' : null,
        $collapsible ? 'portal-filter-bar--collapsible' : null,
    ])->filter()->implode(' ');
    $hasBody = trim((string) $slot) !== '';
    $contentId = $collapsible && $hasBody ? 'portal-filter-bar-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(10)) : null;
@endphp

<section
    {{ $attributes->class($surfaceClasses) }}
    @if ($collapsible && $hasBody)
        x-data="{
            open: @js(! $defaultCollapsed),
            persistKey: @js($persistKey),
            init() {
                if (! this.persistKey) {
                    return;
                }

                try {
                    const storedState = window.localStorage.getItem(this.persistKey);

                    if (storedState !== null) {
                        this.open = storedState === 'true';
                    }
                } catch (error) {
                    // Ignora falhas de persistencia para manter o componente funcional.
                }
            },
            toggle() {
                this.open = ! this.open;

                if (! this.persistKey) {
                    return;
                }

                try {
                    window.localStorage.setItem(this.persistKey, this.open ? 'true' : 'false');
                } catch (error) {
                    // Ignora falhas de persistencia para manter o componente funcional.
                }
            },
        }"
        x-bind:class="{ 'portal-filter-bar--collapsed': !open }"
    @endif
>
    @if ($title || ($showDescription && $description) || isset($actions))
        <div class="portal-filter-bar-header">
            <div>
                @if ($title)
                    <p class="portal-filter-bar-title">{{ $title }}</p>
                @endif

                @if ($showDescription && $description)
                    <p class="portal-filter-bar-description">{{ $description }}</p>
                @endif
            </div>

            @if (isset($actions) || ($collapsible && $hasBody))
                <div class="portal-filter-bar-actions">
                    @if (isset($actions))
                        {{ $actions }}
                    @endif

                    @if ($collapsible && $hasBody)
                        <button
                            type="button"
                            class="portal-filter-bar-toggle"
                            x-on:click="toggle()"
                            aria-controls="{{ $contentId }}"
                            aria-expanded="{{ $defaultCollapsed ? 'false' : 'true' }}"
                            x-bind:aria-expanded="open ? 'true' : 'false'"
                        >
                            <span class="portal-filter-bar-toggle-copy">
                                <span class="portal-filter-bar-toggle-kicker" x-text="open ? 'Area aberta' : 'Area compacta'">
                                    {{ $defaultCollapsed ? 'Area compacta' : 'Area aberta' }}
                                </span>
                                <span class="portal-filter-bar-toggle-label" x-text="open ? 'Recolher filtros' : 'Expandir filtros'">
                                    {{ $defaultCollapsed ? 'Expandir filtros' : 'Recolher filtros' }}
                                </span>
                            </span>
                            <span class="portal-filter-bar-toggle-icon" x-bind:class="{ 'rotate-180': open }" aria-hidden="true">
                                <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" stroke="currentColor" stroke-width="1.7">
                                    <path d="M5.5 7.5 10 12l4.5-4.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if ($hasBody)
        <div
            class="portal-filter-bar-content"
            @if ($collapsible)
                id="{{ $contentId }}"
                x-show="open"
                x-transition.opacity.duration.180ms
                x-cloak
            @endif
        >
            {{ $slot }}
        </div>
    @endif
</section>
