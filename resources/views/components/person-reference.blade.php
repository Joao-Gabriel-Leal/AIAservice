@props([
    'user' => null,
    'name' => null,
    'initials' => null,
    'emptyLabel' => 'Nao atribuido',
    'size' => 'xs',
])

@php
    $resolvedName = filled($name) ? $name : $user?->name;
    $resolvedInitials = $initials
        ?? $user?->initials()
        ?? collect(explode(' ', trim((string) $resolvedName)))
            ->filter()
            ->take(2)
            ->map(fn (string $word) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($word, 0, 1)))
            ->implode('');
@endphp

@if (filled($resolvedName))
    <span
        {{ $attributes->class('ui-person-reference') }}
        title="{{ $resolvedName }}"
        aria-label="{{ $resolvedName }}"
    >
        <x-user-avatar
            :user="$user"
            :name="$resolvedName"
            :initials="$resolvedInitials"
            :alt="$resolvedName"
            :size="$size"
            class="!rounded-full ring-1 ring-slate-200/80 shadow-sm"
        />
    </span>
@else
    <span {{ $attributes->class('ui-person-reference-empty') }}>
        {{ $emptyLabel }}
    </span>
@endif
