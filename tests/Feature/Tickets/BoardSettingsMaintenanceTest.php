<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketFieldType;
use App\Enums\TicketFormOpeningAccessLevel;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\SettingsPage;
use App\Modules\Tickets\Models\ServiceCatalogItem;
use App\Modules\Tickets\Models\TicketField;
use App\Modules\Tickets\Models\TicketForm;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketGroup;
use App\Modules\Tickets\Models\TicketStatus;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoardSettingsMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_uses_internal_tabs_and_can_select_groups(): void
    {
        ['sector' => $sector, 'room' => $room] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->assertSet('openSection', 'board')
            ->call('setOpenSection', 'groups')
            ->assertSet('openSection', 'groups')
            ->call('setOpenSection', 'groups')
            ->assertSet('openSection', 'groups');
    }

    public function test_automation_configuration_is_hidden_while_in_maintenance(): void
    {
        ['sector' => $sector, 'room' => $room] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);

        $this->actingAs($admin)
            ->get(route('tickets.settings'))
            ->assertOk()
            ->assertSeeText('Automacoes em manutencao')
            ->assertDontSeeText('Nova regra')
            ->assertDontSeeText('Criar automacao');
    }

    public function test_group_actions_keep_groups_section_open(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'room' => $room] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);
        $secondGroup = $board->groups()->whereKeyNot($group->id)->orderBy('sort_order')->firstOrFail();

        $component = Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('groupForm.name', 'Retorno')
            ->set('groupForm.color', '#2563eb')
            ->call('addGroup')
            ->assertHasNoErrors()
            ->assertSet('openSection', 'groups')
            ->call('startEditingGroup', $group->id)
            ->assertSet('openSection', 'groups')
            ->set('editGroupForm.name', 'Triagem operacional')
            ->call('updateGroup')
            ->assertHasNoErrors()
            ->assertSet('openSection', 'groups')
            ->call('moveGroupDown', $group->id)
            ->assertHasNoErrors()
            ->assertSet('openSection', 'groups')
            ->call('moveGroupUp', $secondGroup->id)
            ->assertHasNoErrors()
            ->assertSet('openSection', 'groups');

        $newGroup = TicketGroup::query()
            ->where('ticket_board_id', $board->id)
            ->where('name', 'Retorno')
            ->firstOrFail();

        $component
            ->call('confirmDeleteGroup', $newGroup->id)
            ->assertSet('openSection', 'groups')
            ->call('deleteGroup')
            ->assertHasNoErrors()
            ->assertSet('openSection', 'groups');
    }

    public function test_sector_admin_can_edit_a_group_without_breaking_existing_tickets(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status, 'room' => $room] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);
        $ticket = $this->ticketFor($board->id, $sector->id, $room->id, $group->id, $status->id);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->call('startEditingGroup', $group->id)
            ->set('editGroupForm.name', 'Triagem N1')
            ->set('editGroupForm.color', '#0f766e')
            ->set('editGroupForm.is_collapsed_by_default', true)
            ->call('updateGroup')
            ->assertHasNoErrors();

        $group->refresh();
        $ticket->refresh();

        $this->assertSame('Triagem N1', $group->name);
        $this->assertSame('#0f766e', $group->color);
        $this->assertTrue($group->is_collapsed_by_default);
        $this->assertSame('aberto', $group->slug);
        $this->assertSame($group->id, $ticket->ticket_group_id);
    }

    public function test_group_reorder_is_persisted(): void
    {
        ['sector' => $sector, 'board' => $board, 'room' => $room] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);
        $groups = $board->groups()->orderBy('sort_order')->get();
        $firstGroup = $groups[0];
        $secondGroup = $groups[1];

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->call('moveGroupDown', $firstGroup->id)
            ->assertHasNoErrors();

        $orderedIds = TicketGroup::query()
            ->where('ticket_board_id', $board->id)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        $this->assertSame($secondGroup->id, $orderedIds[0]);
        $this->assertSame($firstGroup->id, $orderedIds[1]);
    }

    public function test_group_deletion_requires_explicit_replacement_for_tickets_and_catalog(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status, 'room' => $room] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);
        $replacementGroup = $board->groups()->whereKeyNot($group->id)->firstOrFail();
        $ticket = $this->ticketFor($board->id, $sector->id, $room->id, $group->id, $status->id);
        $catalogItem = ServiceCatalogItem::query()->create([
            'ticket_board_id' => $board->id,
            'ticket_form_id' => $board->forms()->first()?->id,
            'name' => 'Manutencao local',
            'description' => 'Item para teste',
            'default_ticket_group_id' => $group->id,
            'default_priority' => TicketPriority::MEDIUM,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->call('confirmDeleteGroup', $group->id)
            ->set('replacementSelection.group_ticket_group_id', (string) $replacementGroup->id)
            ->set('replacementSelection.group_catalog_group_id', (string) $replacementGroup->id)
            ->call('deleteGroup')
            ->assertHasNoErrors();

        $ticket->refresh();
        $catalogItem->refresh();

        $this->assertSoftDeleted('ticket_groups', ['id' => $group->id]);
        $this->assertSame($replacementGroup->id, $ticket->ticket_group_id);
        $this->assertSame($replacementGroup->id, $catalogItem->default_ticket_group_id);
    }

    public function test_sector_admin_can_promote_another_status_to_default(): void
    {
        ['sector' => $sector, 'board' => $board, 'room' => $room, 'status' => $openStatus] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);
        $candidate = $board->statuses()->whereKeyNot($openStatus->id)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->call('startEditingStatus', $candidate->id)
            ->set('editStatusForm.is_default', true)
            ->set('editStatusForm.is_active', true)
            ->call('updateStatus')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_statuses', [
            'id' => $candidate->id,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('ticket_statuses', [
            'id' => $openStatus->id,
            'is_default' => false,
        ]);
    }

    public function test_default_status_cannot_be_left_without_another_active_default(): void
    {
        ['sector' => $sector, 'room' => $room, 'status' => $defaultStatus] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->call('startEditingStatus', $defaultStatus->id)
            ->set('editStatusForm.is_default', false)
            ->call('updateStatus')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_statuses', [
            'id' => $defaultStatus->id,
            'is_default' => true,
        ]);
    }

    public function test_status_deletion_requires_active_replacement_and_preserves_ticket_validity(): void
    {
        ['sector' => $sector, 'board' => $board, 'room' => $room, 'group' => $group, 'status' => $status] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);
        $replacementStatus = $board->statuses()
            ->whereKeyNot($status->id)
            ->where('is_active', true)
            ->firstOrFail();
        $ticket = $this->ticketFor($board->id, $sector->id, $room->id, $group->id, $status->id);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->call('confirmDeleteStatus', $status->id)
            ->set('replacementSelection.status_replacement_id', (string) $replacementStatus->id)
            ->call('deleteStatus')
            ->assertHasNoErrors();

        $ticket->refresh();

        $this->assertSoftDeleted('ticket_statuses', ['id' => $status->id]);
        $this->assertSame($replacementStatus->id, $ticket->ticket_status_id);
        $this->assertDatabaseHas('ticket_statuses', [
            'id' => $replacementStatus->id,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_last_active_status_cannot_be_deleted(): void
    {
        ['sector' => $sector, 'board' => $board, 'room' => $room, 'status' => $status] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);

        TicketStatus::query()
            ->where('ticket_board_id', $board->id)
            ->whereKeyNot($status->id)
            ->update(['is_active' => false, 'is_default' => false]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->call('confirmDeleteStatus', $status->id)
            ->set('replacementSelection.status_replacement_id', '')
            ->call('deleteStatus')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_statuses', [
            'id' => $status->id,
            'deleted_at' => null,
        ]);
    }

    public function test_sector_admin_can_save_a_form_field_visibility_condition(): void
    {
        ['sector' => $sector, 'board' => $board, 'room' => $room] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);
        $triggerField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Categoria',
            'slug' => 'categoria',
            'type' => TicketFieldType::SELECT,
            'placeholder' => null,
            'help_text' => null,
            'settings' => null,
            'sort_order' => 1,
            'is_required' => false,
            'show_on_board' => false,
            'is_active' => true,
        ]);
        $conditionalField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Patrimonio',
            'slug' => 'patrimonio',
            'type' => TicketFieldType::TEXT,
            'placeholder' => null,
            'help_text' => null,
            'settings' => null,
            'sort_order' => 2,
            'is_required' => false,
            'show_on_board' => false,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('formForm.name', 'Chamado condicional')
            ->set('formForm.description', 'Formulario com campo inteligente')
            ->set('formForm.field_ids', [$triggerField->id, $conditionalField->id])
            ->set('formForm.required_field_ids', [$conditionalField->id])
            ->set("formForm.visibility_conditions.{$conditionalField->id}.enabled", true)
            ->set("formForm.visibility_conditions.{$conditionalField->id}.parent_field_id", (string) $triggerField->id)
            ->set("formForm.visibility_conditions.{$conditionalField->id}.operator", 'equals')
            ->set("formForm.visibility_conditions.{$conditionalField->id}.expected_value", 'hardware')
            ->call('saveForm')
            ->assertHasNoErrors();

        $form = $board->forms()->where('name', 'Chamado condicional')->firstOrFail();

        $this->assertDatabaseHas('ticket_form_fields', [
            'ticket_form_id' => $form->id,
            'ticket_field_id' => $conditionalField->id,
            'is_required' => true,
            'visibility_parent_field_id' => $triggerField->id,
            'visibility_operator' => 'equals',
            'visibility_expected_value' => 'hardware',
        ]);
    }

    public function test_form_visibility_condition_requires_parent_field_inside_the_same_form(): void
    {
        ['sector' => $sector, 'board' => $board, 'room' => $room] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);
        $visibleField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Categoria',
            'slug' => 'categoria',
            'type' => TicketFieldType::TEXT,
            'placeholder' => null,
            'help_text' => null,
            'settings' => null,
            'sort_order' => 1,
            'is_required' => false,
            'show_on_board' => false,
            'is_active' => true,
        ]);
        $outsideField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Base externa',
            'slug' => 'base-externa',
            'type' => TicketFieldType::TEXT,
            'placeholder' => null,
            'help_text' => null,
            'settings' => null,
            'sort_order' => 2,
            'is_required' => false,
            'show_on_board' => false,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('formForm.name', 'Formulario invalido')
            ->set('formForm.field_ids', [$visibleField->id])
            ->set("formForm.visibility_conditions.{$visibleField->id}.enabled", true)
            ->set("formForm.visibility_conditions.{$visibleField->id}.parent_field_id", (string) $outsideField->id)
            ->set("formForm.visibility_conditions.{$visibleField->id}.operator", 'equals')
            ->set("formForm.visibility_conditions.{$visibleField->id}.expected_value", 'x')
            ->call('saveForm')
            ->assertHasErrors(["formForm.visibility_conditions.{$visibleField->id}.parent_field_id"]);
    }

    public function test_form_visibility_condition_cannot_reference_the_same_field(): void
    {
        ['sector' => $sector, 'board' => $board, 'room' => $room] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);
        $field = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Categoria',
            'slug' => 'categoria',
            'type' => TicketFieldType::TEXT,
            'placeholder' => null,
            'help_text' => null,
            'settings' => null,
            'sort_order' => 1,
            'is_required' => false,
            'show_on_board' => false,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('formForm.name', 'Formulario invalido')
            ->set('formForm.field_ids', [$field->id])
            ->set("formForm.visibility_conditions.{$field->id}.enabled", true)
            ->set("formForm.visibility_conditions.{$field->id}.parent_field_id", (string) $field->id)
            ->set("formForm.visibility_conditions.{$field->id}.operator", 'equals')
            ->set("formForm.visibility_conditions.{$field->id}.expected_value", 'x')
            ->call('saveForm')
            ->assertHasErrors(["formForm.visibility_conditions.{$field->id}.parent_field_id"]);
    }

    public function test_sector_admin_can_edit_existing_form_configuration(): void
    {
        ['sector' => $sector, 'board' => $board, 'room' => $room] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);
        $summaryField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Resumo',
            'slug' => 'resumo',
            'type' => TicketFieldType::TEXT,
            'placeholder' => null,
            'help_text' => null,
            'settings' => null,
            'sort_order' => 1,
            'is_required' => false,
            'show_on_board' => true,
            'is_active' => true,
        ]);
        $impactField = TicketField::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Impacto',
            'slug' => 'impacto',
            'type' => TicketFieldType::TEXT,
            'placeholder' => null,
            'help_text' => null,
            'settings' => null,
            'sort_order' => 2,
            'is_required' => false,
            'show_on_board' => true,
            'is_active' => true,
        ]);

        $form = TicketForm::query()->create([
            'ticket_board_id' => $board->id,
            'name' => 'Formulario antigo',
            'description' => 'Descricao anterior',
            'opening_access_level' => TicketFormOpeningAccessLevel::PUBLIC,
            'is_default' => false,
            'is_active' => true,
        ]);
        $form->fields()->sync([
            $summaryField->id => ['is_required' => false, 'sort_order' => 1],
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->call('startEditingForm', $form->id)
            ->assertSet('editingFormId', $form->id)
            ->assertSee('Editando: Formulario antigo')
            ->set('formForm.name', 'Formulario revisado')
            ->set('formForm.description', 'Descricao revisada')
            ->set('formForm.opening_access_level', TicketFormOpeningAccessLevel::MANAGER->value)
            ->set('formForm.field_ids', [$summaryField->id, $impactField->id])
            ->set('formForm.required_field_ids', [$impactField->id])
            ->set('formForm.is_default', true)
            ->set('formForm.is_active', false)
            ->call('saveForm')
            ->assertHasNoErrors();

        $form->refresh();

        $this->assertSame('Formulario revisado', $form->name);
        $this->assertSame('Descricao revisada', $form->description);
        $this->assertSame(TicketFormOpeningAccessLevel::MANAGER, $form->opening_access_level);
        $this->assertTrue($form->is_default);
        $this->assertFalse($form->is_active);
        $this->assertDatabaseHas('ticket_form_fields', [
            'ticket_form_id' => $form->id,
            'ticket_field_id' => $summaryField->id,
            'is_required' => false,
        ]);
        $this->assertDatabaseHas('ticket_form_fields', [
            'ticket_form_id' => $form->id,
            'ticket_field_id' => $impactField->id,
            'is_required' => true,
        ]);
    }

    public function test_sector_admin_can_edit_existing_catalog_item(): void
    {
        ['sector' => $sector, 'board' => $board, 'room' => $room] = $this->maintenanceContext();

        $admin = $this->sectorAdmin($sector, $room);
        $form = $board->forms()->firstOrFail();
        $group = $board->groups()->where('is_closed', false)->latest('id')->firstOrFail();
        $catalogItem = ServiceCatalogItem::query()->create([
            'ticket_board_id' => $board->id,
            'ticket_form_id' => $form->id,
            'name' => 'Catalogo antigo',
            'description' => 'Descricao antiga',
            'default_ticket_group_id' => $board->groups()->firstOrFail()->id,
            'default_priority' => TicketPriority::LOW,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->call('startEditingCatalogItem', $catalogItem->id)
            ->assertSet('editingCatalogItemId', $catalogItem->id)
            ->assertSee('Editando item: Catalogo antigo')
            ->set('catalogForm.name', 'Catalogo revisado')
            ->set('catalogForm.description', 'Descricao revisada')
            ->set('catalogForm.ticket_form_id', $form->id)
            ->set('catalogForm.default_ticket_group_id', $group->id)
            ->set('catalogForm.default_priority', TicketPriority::URGENT->value)
            ->set('catalogForm.is_active', false)
            ->call('saveCatalogItem')
            ->assertHasNoErrors();

        $catalogItem->refresh();

        $this->assertSame('Catalogo revisado', $catalogItem->name);
        $this->assertSame('Descricao revisada', $catalogItem->description);
        $this->assertSame($form->id, $catalogItem->ticket_form_id);
        $this->assertSame($group->id, $catalogItem->default_ticket_group_id);
        $this->assertSame(TicketPriority::URGENT, $catalogItem->default_priority);
        $this->assertFalse($catalogItem->is_active);
    }

    private function maintenanceContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Board',
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Operacoes',
            'slug' => 'operacoes',
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => 'Sala Operacional',
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'company' => $company,
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'group' => $board->groups()->firstOrFail(),
            'status' => $board->statuses()->where('is_default', true)->firstOrFail(),
        ];
    }

    private function sectorAdmin(Sector $sector, Room $room): User
    {
        return User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
            'room_id' => $room->id,
        ]);
    }

    private function ticketFor(int $boardId, int $sectorId, int $roomId, int $groupId, int $statusId): Ticket
    {
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sectorId,
            'room_id' => $roomId,
        ]);

        return Ticket::query()->create([
            'sector_id' => $sectorId,
            'ticket_board_id' => $boardId,
            'ticket_group_id' => $groupId,
            'ticket_status_id' => $statusId,
            'room_id' => $roomId,
            'title' => 'Teste operacional',
            'description' => 'Chamado para manutencao do board.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);
    }
}
