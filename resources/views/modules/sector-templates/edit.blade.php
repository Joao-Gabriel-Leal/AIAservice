<x-layouts.portal title="Editar template de setor" subtitle="Atualize o pacote reutilizavel aplicado no onboarding de novos setores.">
    @include('modules.sector-templates.partials.form', [
        'action' => route('sector-templates.update', $template),
        'method' => 'PUT',
    ])
</x-layouts.portal>
