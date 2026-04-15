<x-layouts.portal title="Novo template de setor" subtitle="Monte um onboarding pronto para reutilizar em novos setores.">
    @include('modules.sector-templates.partials.form', [
        'action' => route('sector-templates.store'),
        'method' => 'POST',
    ])
</x-layouts.portal>
