@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-left">
    <div class="space-y-2">
        <flux:heading size="xl" class="text-[2.15rem] font-semibold tracking-[-0.04em] text-[#445896] md:text-[2.55rem]">
            {{ $title }}
        </flux:heading>
        <flux:subheading class="text-lg leading-7 text-[#7e8db8]">
            {{ $description }}
        </flux:subheading>
    </div>
</div>
