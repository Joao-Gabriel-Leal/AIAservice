@props([
    'user' => null,
    'src' => null,
    'name' => null,
    'initials' => null,
    'alt' => null,
    'size' => 'md',
])

@php
    $resolvedName = $name ?? $user?->name ?? 'Usuario';
    $resolvedInitials = $initials ?? $user?->initials() ?? 'US';
    $resolvedSrc = $src ?? $user?->profilePhotoUrl();
    $resolvedAlt = $alt ?? "Foto de perfil de {$resolvedName}";

    $sizeClasses = match ($size) {
        'xs' => 'size-8 text-[11px]',
        'sm' => 'size-10 text-xs',
        'md' => 'size-12 text-sm',
        'lg' => 'size-16 text-base',
        'xl' => 'size-20 text-xl',
        default => 'size-12 text-sm',
    };
@endphp

@if ($resolvedSrc)
    <img
        src="{{ $resolvedSrc }}"
        alt="{{ $resolvedAlt }}"
        {{ $attributes->class([$sizeClasses, 'shrink-0 rounded-2xl object-cover bg-slate-200']) }}
    >
@else
    <div
        {{ $attributes->class([$sizeClasses, 'shrink-0 rounded-2xl bg-sky-500/15 text-center font-semibold uppercase tracking-[0.18em] text-sky-700 flex items-center justify-center']) }}
        aria-label="{{ $resolvedAlt }}"
    >
        {{ $resolvedInitials }}
    </div>
@endif
