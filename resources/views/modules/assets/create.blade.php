<x-layouts.portal title="Novo patrimonio" subtitle="Cadastro inicial com setor, sala, status e colaborador opcional.">
    <form method="POST" action="{{ route('assets.store') }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-4 text-sm">
            <a href="{{ route('assets.index') }}" class="text-slate-500 hover:text-slate-900">Patrimonios</a>
            <span class="text-slate-300">/</span>
            <span class="font-medium text-slate-900">Novo cadastro</span>
        </div>

        @include('modules.assets.form', ['includeAllocation' => true])

        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
            <a href="{{ route('assets.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</a>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Cadastrar patrimonio</button>
        </div>
    </form>
</x-layouts.portal>
