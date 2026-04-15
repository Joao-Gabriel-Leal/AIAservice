@props([
    'sidebar' => false,
])

@php($brandName = config('app.name', 'AIA Service'))

@if($sidebar)
    <flux:sidebar.brand name="{{ $brandName }}" {{ $attributes }}>
        <x-slot name="logo" class="flex h-12 w-[6.75rem] items-center justify-center overflow-hidden rounded-[0.95rem]">
            <x-app-logo-icon class="h-full w-full" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ $brandName }}" {{ $attributes }}>
        <x-slot name="logo" class="flex h-12 w-[6.75rem] items-center justify-center overflow-hidden rounded-[0.95rem]">
            <x-app-logo-icon class="h-full w-full" />
        </x-slot>
    </flux:brand>
@endif
