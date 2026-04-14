<x-layouts.portal title="Nova empresa">
    <form method="POST" action="{{ route('companies.store') }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @include('modules.companies.form')

        <div class="flex justify-end gap-3">
            <a href="{{ route('companies.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</a>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Salvar empresa</button>
        </div>
    </form>
</x-layouts.portal>
