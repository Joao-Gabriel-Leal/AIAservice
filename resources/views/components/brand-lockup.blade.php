@props([
    'alt' => config('app.name', 'AIA Service'),
    'subtitle' => 'Gestao interna modular',
])

<div
    {{ $attributes->class('inline-flex items-center gap-3 rounded-[1.65rem] border border-white/12 bg-[linear-gradient(135deg,rgba(9,18,38,0.94),rgba(24,35,82,0.84))] px-4 py-3 text-left shadow-[0_26px_60px_-34px_rgba(2,6,23,0.92)] backdrop-blur-md') }}
    role="img"
    aria-label="{{ $alt }} - {{ $subtitle }}"
>
    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[1.15rem] border border-white/12 bg-white/5 p-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.1)]">
        <x-brand-mark class="h-full w-full" :alt="$alt" />
    </span>

    <span class="min-w-0">
        <span class="block text-[0.64rem] font-semibold uppercase tracking-[0.42em] text-[#8fb8ff]">
            AIA
        </span>
        <span class="mt-1 block text-[1.08rem] font-black uppercase tracking-[0.24em] leading-none text-white">
            Service
        </span>
        <span class="mt-1.5 block max-w-[10rem] text-[0.76rem] font-medium leading-[1.45] text-[#cadcff]">
            {{ $subtitle }}
        </span>
    </span>
</div>
