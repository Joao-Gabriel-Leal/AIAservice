@props([
    'formId',
    'action',
    'searchName' => 'search',
    'searchValue' => '',
    'placeholder' => 'Buscar',
    'clearHref' => null,
    'hasActiveFilters' => false,
    'inputId' => null,
])

@php($inputId = $inputId ?: $formId.'-search')

<div class="sticky top-0 z-20 -mx-1 bg-slate-50/95 px-1 py-2 backdrop-blur dark:bg-[#07101f]/92">
    <form
        id="{{ $formId }}"
        method="GET"
        action="{{ $action }}"
        {{ $attributes->class('flex flex-col gap-2 sm:flex-row sm:items-center') }}
    >
        <label for="{{ $inputId }}" class="sr-only">Buscar</label>
        <input
            id="{{ $inputId }}"
            type="text"
            name="{{ $searchName }}"
            value="{{ $searchValue }}"
            placeholder="{{ $placeholder }}"
            class="ui-input min-h-11 w-full sm:max-w-xl"
        >

        <div class="flex shrink-0 gap-2">
            <button type="submit" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Buscar</button>
            @if ($hasActiveFilters && $clearHref)
                <a href="{{ $clearHref }}" class="ui-action ui-action-secondary rounded-2xl px-4 py-3 text-sm">Limpar</a>
            @endif
        </div>

        {{ $slot }}
    </form>
</div>
