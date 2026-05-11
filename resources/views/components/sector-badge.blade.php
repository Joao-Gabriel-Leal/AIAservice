@props([
    'sector' => null,
    'mode' => 'chip',
    'prefix' => null,
])

@php
    $label = trim(implode(' ', array_filter([$prefix, $sector?->name ?? 'Sem setor'])));
    $color = $sector?->displayColor() ?? '#64748B';
    $softColor = $sector?->softColor() ?? '#E2E8F0';
    $borderColor = $sector?->borderColor() ?? '#CBD5E1';
    $sectorStyle = "--sector-color: {$color}; --sector-soft-color: {$softColor}; --sector-border-color: {$borderColor};";
    $slotContent = trim((string) $slot);
@endphp

@if ($mode === 'dot')
    <span {{ $attributes->class('sector-badge sector-badge-dot inline-flex items-center gap-2 text-sm text-slate-700')->merge(['style' => $sectorStyle]) }}>
        <span class="sector-badge-color-dot size-2.5 shrink-0 rounded-full"></span>
        <span class="sector-badge-label font-medium text-slate-900">{{ $label }}</span>
        @if ($slotContent !== '')
            <span class="sector-badge-slot text-slate-500">{{ $slot }}</span>
        @endif
    </span>
@elseif ($mode === 'label-dot')
    <span
        {{ $attributes->class('sector-badge sector-badge-pill inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]')->merge(['style' => $sectorStyle]) }}
    >
        <span class="sector-badge-color-dot size-2 shrink-0 rounded-full"></span>
        <span>{{ $label }}</span>
        @if ($slotContent !== '')
            <span class="opacity-80">{{ $slot }}</span>
        @endif
    </span>
@else
    <span
        {{ $attributes->class('sector-badge sector-badge-pill inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium')->merge(['style' => $sectorStyle]) }}
    >
        <span class="sector-badge-color-dot size-2 shrink-0 rounded-full"></span>
        <span>{{ $label }}</span>
        @if ($slotContent !== '')
            <span class="opacity-80">{{ $slot }}</span>
        @endif
    </span>
@endif
