@props([
    'sidebar' => false,
])

@php($brandName = config('app.name', 'AIA Service'))

@if($sidebar)
    <flux:sidebar.brand name="{{ $brandName }}" {{ $attributes }}>
        <x-slot name="logo" class="flex h-12 w-12 items-center justify-center">
            <x-app-logo-icon class="size-12 text-base" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ $brandName }}" {{ $attributes }}>
        <x-slot name="logo" class="flex h-12 w-12 items-center justify-center">
            <x-app-logo-icon class="size-12 text-base" />
        </x-slot>
    </flux:brand>
@endif
