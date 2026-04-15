<?php

namespace Tests\Feature\Management;

use App\Enums\TicketAutomationActionType;
use App\Enums\TicketAutomationConditionField;
use App\Enums\TicketAutomationConditionOperator;
use App\Enums\TicketAutomationRunMode;
use App\Enums\TicketAutomationTrigger;
use App\Enums\TicketFieldType;
use App\Enums\TicketPriority;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\SectorTemplates\Models\SectorTemplate;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectorTemplateProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_sector_without_template_keeps_default_provisioning(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Base',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('sectors.store'), [
                'company_id' => $company->id,
                'name' => 'Financeiro',
                'color' => '#1D4ED8',
                'description' => 'Setor financeiro',
                'is_active' => '1',
            ])
            ->assertRedirect(route('sectors.index', absolute: false));

        $sector = Sector::query()->with('board.forms', 'board.catalogItems', 'board.automationRules', 'board.slaPolicy.targets')->firstOrFail();
        $board = $sector->board;

        $this->assertNotNull($board);
        $this->assertSame(1, $board->forms->count());
        $this->assertSame('Abertura padrao', $board->forms->first()->name);
        $this->assertSame(1, $board->catalogItems->count());
        $this->assertSame('Solicitacao geral', $board->catalogItems->first()->name);
        $this->assertSame(0, $board->automationRules->count());
        $this->assertNotNull($board->slaPolicy);
        $this->assertCount(count(TicketPriority::cases()), $board->slaPolicy->targets);
    }

    public function test_creating_sector_with_template_copies_form_catalog_automation_and_sla(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Template',
            'is_active' => true,
        ]);

        $template = $this->createTemplate();

        $this->actingAs($admin)
            ->post(route('sectors.store'), [
                'company_id' => $company->id,
                'sector_template_id' => $template->id,
                'name' => 'Recursos Humanos',
                'color' => '#0F766E',
                'description' => 'Setor de RH',
                'is_active' => '1',
            ])
            ->assertRedirect(route('sectors.index', absolute: false));

        $sector = Sector::query()->with([
            'template',
            'board.groups',
            'board.statuses',
            'board.forms.fields.options',
            'board.catalogItems.form',
            'board.automationRules.conditions',
            'board.automationRules.actions',
            'board.slaPolicy.targets',
        ])->firstOrFail();

        $board = $sector->board;
        $form = $board->forms->sole();
        $catalog = $board->catalogItems->sole();
        $rule = $board->automationRules->sole();
        $group = $board->groups->firstWhere('slug', 'em-andamento');
        $status = $board->statuses->firstWhere('slug', 'em-atendimento');
        $newStatus = $board->statuses->firstWhere('slug', 'novo');

        $this->assertSame($template->id, $sector->sector_template_id);
        $this->assertSame('Onboarding RH', $form->name);
        $this->assertSame(2, $form->fields->count());
        $this->assertSame('Admissao guiada', $catalog->name);
        $this->assertSame($form->id, $catalog->ticket_form_id);
        $this->assertSame($group->id, $catalog->default_ticket_group_id);
        $this->assertSame(TicketPriority::HIGH, $catalog->default_priority);

        $statusCondition = $rule->conditions->firstWhere('field', TicketAutomationConditionField::STATUS_ID);
        $changeGroup = $rule->actions->firstWhere('action', TicketAutomationActionType::CHANGE_GROUP);
        $changeStatus = $rule->actions->firstWhere('action', TicketAutomationActionType::CHANGE_STATUS);
        $reopen = $rule->actions->firstWhere('action', TicketAutomationActionType::REOPEN_TICKET);

        $this->assertSame([$newStatus->id], $statusCondition->value);
        $this->assertSame($group->id, data_get($changeGroup->payload, 'group_id'));
        $this->assertSame($status->id, data_get($changeStatus->payload, 'status_id'));
        $this->assertSame($newStatus->id, data_get($reopen->payload, 'target_status_id'));
        $this->assertArrayNotHasKey('group_slug', $changeGroup->payload);
        $this->assertArrayNotHasKey('status_slug', $changeStatus->payload);

        $this->assertTrue($board->slaPolicy->is_active);
        $this->assertSame(45, $board->slaPolicy->targets->firstWhere('priority', TicketPriority::HIGH)->first_response_minutes);
        $this->assertSame(240, $board->slaPolicy->targets->firstWhere('priority', TicketPriority::HIGH)->resolution_minutes);
    }

    public function test_inactive_template_cannot_be_selected_when_creating_sector(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Bloqueio',
            'is_active' => true,
        ]);

        $template = $this->createTemplate(false);

        $this->actingAs($admin)
            ->from(route('sectors.create'))
            ->post(route('sectors.store'), [
                'company_id' => $company->id,
                'sector_template_id' => $template->id,
                'name' => 'Juridico',
                'color' => '#7C3AED',
                'description' => 'Setor juridico',
                'is_active' => '1',
            ])
            ->assertRedirect(route('sectors.create', absolute: false))
            ->assertSessionHasErrors('sector_template_id');

        $this->assertSame(0, Sector::query()->count());
    }

    public function test_updating_sector_after_template_provisioning_does_not_duplicate_records(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $company = Company::query()->create([
            'name' => 'Empresa Update',
            'is_active' => true,
        ]);

        $template = $this->createTemplate();

        $this->actingAs($admin)->post(route('sectors.store'), [
            'company_id' => $company->id,
            'sector_template_id' => $template->id,
            'name' => 'Operacoes',
            'color' => '#1E40AF',
            'description' => 'Setor operacional',
            'is_active' => '1',
        ]);

        $sector = Sector::query()->with('board')->firstOrFail();
        $board = $sector->board;
        $before = [
            'forms' => $board->forms()->count(),
            'fields' => $board->fields()->count(),
            'catalog' => $board->catalogItems()->count(),
            'automation' => $board->automationRules()->count(),
            'sla_targets' => $board->slaPolicy()->firstOrFail()->targets()->count(),
        ];

        $this->actingAs($admin)
            ->put(route('sectors.update', $sector), [
                'company_id' => $company->id,
                'sector_template_id' => $template->id,
                'name' => 'Operacoes e Campo',
                'color' => '#1E40AF',
                'description' => 'Setor operacional atualizado',
                'is_active' => '1',
            ])
            ->assertRedirect(route('sectors.index', absolute: false));

        $board->refresh();

        $this->assertSame($before['forms'], $board->forms()->count());
        $this->assertSame($before['fields'], $board->fields()->count());
        $this->assertSame($before['catalog'], $board->catalogItems()->count());
        $this->assertSame($before['automation'], $board->automationRules()->count());
        $this->assertSame($before['sla_targets'], $board->slaPolicy()->firstOrFail()->targets()->count());
    }

    private function createTemplate(bool $isActive = true): SectorTemplate
    {
        $template = SectorTemplate::query()->create([
            'name' => 'Template RH',
            'slug' => 'template-rh',
            'description' => 'Template para onboarding de RH.',
            'form_name' => 'Onboarding RH',
            'form_description' => 'Formulario base para processos de RH.',
            'is_active' => $isActive,
        ]);

        $template->fields()->create([
            'name' => 'Nome do colaborador',
            'slug' => 'nome-do-colaborador',
            'type' => TicketFieldType::TEXT,
            'placeholder' => 'Informe o nome completo',
            'help_text' => 'Campo obrigatorio',
            'options' => null,
            'sort_order' => 1,
            'is_required' => true,
            'show_on_board' => true,
            'is_active' => true,
        ]);

        $template->fields()->create([
            'name' => 'Tipo de admissao',
            'slug' => 'tipo-de-admissao',
            'type' => TicketFieldType::SELECT,
            'placeholder' => null,
            'help_text' => 'Selecione o fluxo',
            'options' => [
                ['label' => 'CLT', 'value' => 'clt', 'sort_order' => 1],
                ['label' => 'Estagio', 'value' => 'estagio', 'sort_order' => 2],
            ],
            'sort_order' => 2,
            'is_required' => true,
            'show_on_board' => true,
            'is_active' => true,
        ]);

        $template->catalogItems()->create([
            'name' => 'Admissao guiada',
            'description' => 'Fluxo padrao para nova admissao.',
            'default_ticket_group_slug' => 'em-andamento',
            'default_priority' => TicketPriority::HIGH,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $rule = $template->automationRules()->create([
            'name' => 'Tratar admissao nova',
            'description' => 'Move e comenta automaticamente.',
            'trigger' => TicketAutomationTrigger::TICKET_CREATED,
            'run_mode' => TicketAutomationRunMode::SYNC,
            'trigger_settings' => null,
            'cooldown_minutes' => 10,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $rule->conditions()->create([
            'field' => TicketAutomationConditionField::STATUS_ID,
            'operator' => TicketAutomationConditionOperator::IN,
            'value' => ['novo'],
            'sort_order' => 1,
        ]);

        $rule->actions()->create([
            'action' => TicketAutomationActionType::CHANGE_GROUP,
            'payload' => ['group_slug' => 'em-andamento'],
            'sort_order' => 1,
        ]);

        $rule->actions()->create([
            'action' => TicketAutomationActionType::CHANGE_STATUS,
            'payload' => ['status_slug' => 'em-atendimento'],
            'sort_order' => 2,
        ]);

        $rule->actions()->create([
            'action' => TicketAutomationActionType::ADD_SYSTEM_MESSAGE,
            'payload' => ['message' => 'Fluxo de admissao iniciado automaticamente.'],
            'sort_order' => 3,
        ]);

        $rule->actions()->create([
            'action' => TicketAutomationActionType::REOPEN_TICKET,
            'payload' => [
                'status_slug' => 'novo',
                'message' => 'Chamado reaberto automaticamente.',
            ],
            'sort_order' => 4,
        ]);

        $policy = $template->slaPolicy()->create([
            'is_active' => true,
        ]);

        foreach (TicketPriority::cases() as $priority) {
            $policy->targets()->create([
                'priority' => $priority,
                'first_response_minutes' => $priority === TicketPriority::HIGH ? 45 : 60,
                'resolution_minutes' => $priority === TicketPriority::HIGH ? 240 : 480,
            ]);
        }

        return $template;
    }
}
