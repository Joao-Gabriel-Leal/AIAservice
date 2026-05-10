<x-layouts.portal title="Novo artigo" header-variant="none">
    <div class="space-y-6">
        <x-portal.page-intro
            variant="compact"
            eyebrow="Base de conhecimento"
            title="Novo artigo"
            description="Cadastre um conteudo consultivo com resumo, detalhes, anexos e foto de capa."
        />

        <form method="POST" action="{{ $formAction ?? route('knowledge-base.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @if (($formMethod ?? 'POST') !== 'POST')
                @method($formMethod)
            @endif

            @include('modules.knowledge-base.form')

            <div class="flex flex-col justify-end gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row">
                <a href="{{ ($sourceTicket ?? null) ? route('tickets.show', $sourceTicket) : route('knowledge-base.manage') }}" class="ui-action ui-action-secondary px-4 py-3 text-sm">Cancelar</a>
                <button type="submit" class="ui-action ui-action-primary px-4 py-3 text-sm">
                    {{ ($sourceTicket ?? null) ? 'Salvar a partir do chamado' : 'Salvar artigo' }}
                </button>
            </div>
        </form>
    </div>
</x-layouts.portal>
