<x-layouts.portal :title="'Editar licenca: '.$license->displayName()" subtitle="Atualize quantidade, datas e dados operacionais sem perder as atribuicoes atuais.">
    <form method="POST" action="{{ route('licenses.update', $license) }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-4 text-sm">
            <a href="{{ route('licenses.index') }}" class="text-slate-500 hover:text-slate-900">Licencas</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('licenses.show', $license) }}" class="text-slate-500 hover:text-slate-900">{{ $license->displayName() }}</a>
            <span class="text-slate-300">/</span>
            <span class="font-medium text-slate-900">Editar</span>
        </div>

        @include('modules.licenses.form')

        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
            <a href="{{ route('licenses.show', $license) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</a>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Salvar alteracoes</button>
        </div>
    </form>
</x-layouts.portal>
