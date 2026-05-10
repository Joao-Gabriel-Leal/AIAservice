@props([
    'label',
    'formId',
    'name',
    'allLabel' => 'Todos',
    'minWidth' => 'min-w-40',
    'autoSubmit' => true,
])

<div class="flex {{ $minWidth }} flex-col gap-2">
    <span>{{ $label }}</span>
    <select
        form="{{ $formId }}"
        name="{{ $name }}"
        {{ $attributes->class('ui-native-select min-h-10 w-full text-xs text-slate-700') }}
        @if ($autoSubmit)
            onchange="this.form?.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
        @endif
    >
        <option value="">{{ $allLabel }}</option>
        {{ $slot }}
    </select>
</div>
