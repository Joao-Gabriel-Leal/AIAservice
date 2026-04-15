@props([
    'sidebar' => false,
])

@php($brandName = config('app.name', 'AIA Service'))

@if($sidebar)
    <flux:sidebar.brand name="{{ $brandName }}" {{ $attributes }}>
        <x-slot name="logo" class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-[0.95rem] shadow-[0_18px_34px_-22px_rgba(15,23,42,0.58)]">
            <x-app-logo-icon class="h-full w-full" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ $brandName }}" {{ $attributes }}>
        <x-slot name="logo" class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-[0.95rem] shadow-[0_18px_34px_-22px_rgba(15,23,42,0.58)]">
            <x-app-logo-icon class="h-full w-full" />
        </x-slot>
    </flux:brand>
@endif
