<?php

namespace App\Modules\SectorTemplates\Http\Controllers;

use App\Enums\TicketAutomationActionType;
use App\Enums\TicketAutomationConditionField;
use App\Enums\TicketAutomationConditionOperator;
use App\Enums\TicketAutomationTrigger;
use App\Enums\TicketFieldType;
use App\Enums\TicketPriority;
use App\Http\Controllers\Controller;
use App\Modules\SectorTemplates\Http\Requests\SectorTemplateRequest;
use App\Modules\SectorTemplates\Models\SectorTemplate;
use App\Modules\SectorTemplates\Services\SectorTemplatePersistenceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SectorTemplateController extends Controller
{
    public function __construct(
        private readonly SectorTemplatePersistenceService $persistenceService,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', SectorTemplate::class);

        return view('modules.sector-templates.index', [
            'templates' => SectorTemplate::query()->withCount('sectors')->orderBy('name')->paginate(12),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', SectorTemplate::class);

        return view('modules.sector-templates.create', $this->formViewData(new SectorTemplate()));
    }

    public function store(SectorTemplateRequest $request): RedirectResponse
    {
        $this->authorize('create', SectorTemplate::class);

        $this->persistenceService->save(null, $request->validated());

        return redirect()->route('sector-templates.index')->with('status', 'Template criado com sucesso.');
    }

    public function edit(SectorTemplate $sectorTemplate): View
    {
        $this->authorize('update', $sectorTemplate);
        $sectorTemplate->load(['fields', 'catalogItems', 'automationRules.conditions', 'automationRules.actions', 'slaPolicy.targets']);

        return view('modules.sector-templates.edit', $this->formViewData($sectorTemplate));
    }

    public function update(SectorTemplateRequest $request, SectorTemplate $sectorTemplate): RedirectResponse
    {
        $this->authorize('update', $sectorTemplate);

        $this->persistenceService->save($sectorTemplate, $request->validated());

        return redirect()->route('sector-templates.index')->with('status', 'Template atualizado com sucesso.');
    }

    public function destroy(SectorTemplate $sectorTemplate): RedirectResponse
    {
        $this->authorize('delete', $sectorTemplate);
        $sectorTemplate->delete();

        return redirect()->route('sector-templates.index')->with('status', 'Template removido com sucesso.');
    }

    private function formViewData(SectorTemplate $template): array
    {
        return [
            'template' => $template,
            'fieldTypes' => TicketFieldType::cases(),
            'priorities' => TicketPriority::cases(),
            'automationTriggers' => TicketAutomationTrigger::cases(),
            'conditionFields' => TicketAutomationConditionField::cases(),
            'conditionOperators' => TicketAutomationConditionOperator::cases(),
            'actionTypes' => collect(TicketAutomationActionType::cases())
                ->reject(fn (TicketAutomationActionType $action) => $action === TicketAutomationActionType::ASSIGN_FIXED_ASSIGNEE)
                ->values(),
            'groupOptions' => SectorTemplate::defaultGroupOptions(),
            'statusOptions' => SectorTemplate::defaultStatusOptions(),
        ];
    }
}
