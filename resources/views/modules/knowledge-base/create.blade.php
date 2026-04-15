<x-layouts.portal title="Novo artigo">
    <div class="space-y-6">
        <form method="POST" action="{{ route('knowledge-base.store') }}" enctype="multipart/form-data" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            @include('modules.knowledge-base.form')

            <div class="flex justify-end gap-3">
                <a href="{{ route('knowledge-base.manage') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</a>
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">Salvar artigo</button>
            </div>
        </form>
    </div>
</x-layouts.portal>
