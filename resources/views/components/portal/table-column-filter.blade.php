@props([
    'label',
    'formId',
    'name',
    'allLabel' => 'Todos',
    'minWidth' => 'min-w-40',
    'autoSubmit' => true,
    'variant' => 'stacked',
])

@if ($variant === 'inline')
    <div class="relative inline-flex {{ $minWidth }} items-center">
        <select
            form="{{ $formId }}"
            name="{{ $name }}"
            aria-label="{{ $label }}"
            {{ $attributes->class('h-9 max-w-56 cursor-pointer appearance-none truncate rounded-xl border border-transparent bg-transparent py-1.5 pl-2 pr-7 text-sm font-medium text-slate-600 outline-none hover:bg-white/70 hover:text-slate-900 focus-visible:border-slate-300 focus-visible:bg-white focus-visible:ring-2 focus-visible:ring-sky-500/20 dark:text-slate-300 dark:hover:bg-slate-900/70 dark:hover:text-slate-100 dark:focus-visible:border-slate-600 dark:focus-visible:bg-slate-900/90') }}
            @if ($autoSubmit)
                onchange="this.form?.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
            @endif
        >
            <option value="">{{ $allLabel }}</option>
            {{ $slot }}
        </select>

        <flux:icon.chevron-down variant="micro" class="pointer-events-none absolute right-2.5 top-1/2 size-3.5 -translate-y-1/2 text-slate-500 dark:text-slate-400" />
    </div>
@else
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
@endif
