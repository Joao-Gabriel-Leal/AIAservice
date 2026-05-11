@props(['href'])

<a
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => 'ui-action ui-action-secondary min-w-12 justify-center rounded-2xl px-4 py-3 text-sm',
        'aria-label' => 'Exportar Excel',
        'title' => 'Exportar Excel',
    ]) }}
>
    <svg viewBox="0 0 24 24" fill="none" class="size-5 text-emerald-700" aria-hidden="true">
        <path d="M13.5 3.5H17A2.5 2.5 0 0 1 19.5 6v12a2.5 2.5 0 0 1-2.5 2.5H7A2.5 2.5 0 0 1 4.5 18v-2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M13.5 3.5V8H18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M13 11h4M13 14.5h4M13 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        <path d="M4.5 7.5h7v7h-7z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
        <path d="m6.5 9.5 3 3M9.5 9.5l-3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
    </svg>
    <span class="sr-only">Exportar Excel</span>
</a>
