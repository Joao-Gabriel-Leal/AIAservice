<x-layouts.portal title="Editar artigo" header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Base de conhecimento"
            title="Editar artigo"
            description="Atualize o conteudo, a visibilidade, os anexos e a foto de capa."
        />

        <form method="POST" action="{{ $formAction ?? route('knowledge-base.update', $article) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method($formMethod ?? 'PUT')

            @include('modules.knowledge-base.form')

            <div class="flex flex-col justify-end gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row">
                <a href="{{ route('knowledge-base.manage') }}" class="ui-action ui-action-secondary px-4 py-3 text-sm">Cancelar</a>
                <button type="submit" class="ui-action ui-action-primary px-4 py-3 text-sm">Atualizar artigo</button>
            </div>
        </form>
    </div>
</x-layouts.portal>
