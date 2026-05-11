<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\SettingsPage;
use App\Modules\Tickets\Livewire\ShowPage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketMessageTemplate;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TicketMessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_template_can_be_created_and_used_by_operator(): void
    {
        Notification::fake();

        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $manager = User::factory()->create([
            'role' => UserRole::SECTOR_ADMIN,
            'sector_id' => $sector->id,
        ]);
        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'assignee_id' => $operator->id,
        ]);

        Livewire::actingAs($manager)
            ->test(SettingsPage::class, ['board' => $board])
            ->set('templateForm.channel', TicketMessageTemplate::CHANNEL_PUBLIC)
            ->set('templateForm.name', 'Pedir print')
            ->set('templateForm.body', 'Pode enviar um print do erro?')
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $template = TicketMessageTemplate::query()->firstOrFail();

        Livewire::actingAs($operator)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('message', 'Estamos verificando.')
            ->call('applyMessageTemplate', $template->id)
            ->assertSet('message', "Estamos verificando.\n\nPode enviar um print do erro?")
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => $operator->id,
            'message' => "Estamos verificando.\n\nPode enviar um print do erro?",
            'is_internal' => false,
        ]);
    }

    public function test_templates_are_available_in_at_autocomplete_without_composer_chips(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $operator = User::factory()->create([
            'name' => 'Operador Atual',
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        User::factory()->create([
            'name' => 'Tecnico Marcavel',
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'assignee_id' => $operator->id,
        ]);

        TicketMessageTemplate::query()->create([
            'ticket_board_id' => $board->id,
            'user_id' => null,
            'channel' => TicketMessageTemplate::CHANNEL_PUBLIC,
            'name' => 'Resposta rapida',
            'body' => 'Retorno padrao para o solicitante.',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        TicketMessageTemplate::query()->create([
            'ticket_board_id' => $board->id,
            'user_id' => $operator->id,
            'channel' => TicketMessageTemplate::CHANNEL_PUBLIC,
            'name' => 'Meu publico',
            'body' => 'Resposta pessoal para o solicitante.',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        TicketMessageTemplate::query()->create([
            'ticket_board_id' => $board->id,
            'user_id' => null,
            'channel' => TicketMessageTemplate::CHANNEL_INTERNAL,
            'name' => 'Nota interna',
            'body' => 'Alinhar internamente antes do retorno.',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        TicketMessageTemplate::query()->create([
            'ticket_board_id' => $board->id,
            'user_id' => $operator->id,
            'channel' => TicketMessageTemplate::CHANNEL_INTERNAL,
            'name' => 'Minha nota interna',
            'body' => 'Checklist interno pessoal.',
            'is_active' => true,
            'sort_order' => 3,
        ]);
        TicketMessageTemplate::query()->create([
            'ticket_board_id' => $board->id,
            'user_id' => null,
            'channel' => TicketMessageTemplate::CHANNEL_PUBLIC,
            'name' => 'Resposta inativa',
            'body' => 'Nao deve aparecer.',
            'is_active' => false,
            'sort_order' => 4,
        ]);
        TicketMessageTemplate::query()->create([
            'ticket_board_id' => $board->id,
            'user_id' => User::factory()->create([
                'role' => UserRole::TECHNICIAN,
                'sector_id' => $sector->id,
            ])->id,
            'channel' => TicketMessageTemplate::CHANNEL_PUBLIC,
            'name' => 'Outro pessoal',
            'body' => 'Nao deve aparecer para outro operador.',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $component = Livewire::actingAs($operator)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->assertSee('ticketComposerAutocomplete', false)
            ->assertSee('Resposta rapida')
            ->assertSee('Meu publico')
            ->assertSee('Nota interna')
            ->assertSee('Minha nota interna')
            ->assertDontSee('Resposta inativa')
            ->assertDontSee('Outro pessoal')
            ->assertDontSee('ticket-template-strip', false)
            ->assertDontSee('ticket-template-pill', false);

        $html = $component->html();
        $publicComposer = $this->htmlFragment($html, 'wire:submit="sendMessage"', '</form>');
        $internalComposer = $this->htmlFragment($html, 'wire:submit="sendInternalUpdate"', '</form>');

        $this->assertStringContainsString('Resposta rapida', $publicComposer);
        $this->assertStringContainsString('Meu publico', $publicComposer);
        $this->assertStringNotContainsString('users:', $publicComposer);
        $this->assertStringNotContainsString('Tecnico Marcavel', $publicComposer);
        $this->assertStringNotContainsString('Nota interna', $publicComposer);

        $this->assertStringContainsString('users:', $internalComposer);
        $this->assertStringContainsString('Tecnico Marcavel', $internalComposer);
        $this->assertStringContainsString('Nota interna', $internalComposer);
        $this->assertStringContainsString('Minha nota interna', $internalComposer);
        $this->assertStringNotContainsString('Resposta rapida', $internalComposer);
    }

    public function test_operator_can_create_edit_and_delete_personal_template(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'assignee_id' => $operator->id,
        ]);

        Livewire::actingAs($operator)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->set('personalTemplateForm.channel', TicketMessageTemplate::CHANNEL_INTERNAL)
            ->set('personalTemplateForm.name', 'Escalar dev')
            ->set('personalTemplateForm.body', 'Validar com desenvolvimento.')
            ->call('savePersonalTemplate')
            ->assertHasNoErrors();

        $template = TicketMessageTemplate::query()
            ->where('user_id', $operator->id)
            ->firstOrFail();

        Livewire::actingAs($operator)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->call('startEditingPersonalTemplate', $template->id)
            ->set('personalTemplateForm.name', 'Escalar time dev')
            ->set('personalTemplateForm.body', 'Validar com desenvolvimento e registrar retorno.')
            ->call('savePersonalTemplate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_message_templates', [
            'id' => $template->id,
            'user_id' => $operator->id,
            'name' => 'Escalar time dev',
            'body' => 'Validar com desenvolvimento e registrar retorno.',
        ]);

        Livewire::actingAs($operator)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->call('deletePersonalTemplate', $template->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('ticket_message_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_personal_template_is_not_visible_or_usable_by_other_operator(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $owner = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $otherOperator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'assignee_id' => $otherOperator->id,
        ]);
        $template = TicketMessageTemplate::query()->create([
            'ticket_board_id' => $board->id,
            'user_id' => $owner->id,
            'channel' => TicketMessageTemplate::CHANNEL_PUBLIC,
            'name' => 'Somente meu',
            'body' => 'Texto privado do operador.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Livewire::actingAs($otherOperator)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->assertDontSee('Somente meu');

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($otherOperator)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->call('applyMessageTemplate', $template->id);
    }

    public function test_requester_does_not_see_or_use_templates(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $ticket = $this->ticketFor($requester, [
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'assignee_id' => $operator->id,
        ]);
        $template = TicketMessageTemplate::query()->create([
            'ticket_board_id' => $board->id,
            'user_id' => null,
            'channel' => TicketMessageTemplate::CHANNEL_PUBLIC,
            'name' => 'Resposta padrao',
            'body' => 'Texto de operador.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $ticket])
            ->assertDontSee('Resposta padrao')
            ->assertDontSee('Templates pessoais')
            ->assertDontSee('Salvar template')
            ->call('applyMessageTemplate', $template->id)
            ->assertForbidden();
    }

    private function ticketContext(string $sectorName = 'Suporte Templates'): array
    {
        $company = Company::query()->create([
            'name' => "Empresa {$sectorName}",
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => $sectorName,
            'slug' => str($sectorName)->slug()->toString(),
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => "Sala {$sectorName}",
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'company' => $company,
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'group' => $board->groups()->where('is_closed', false)->orderBy('sort_order')->firstOrFail(),
            'status' => $board->statuses()->where('is_closed', false)->orderBy('sort_order')->firstOrFail(),
        ];
    }

    private function ticketFor(User $requester, array $attributes): Ticket
    {
        return Ticket::query()->create([
            'sector_id' => $attributes['sector_id'],
            'ticket_board_id' => $attributes['ticket_board_id'],
            'ticket_group_id' => $attributes['ticket_group_id'],
            'ticket_status_id' => $attributes['ticket_status_id'],
            'service_catalog_item_id' => $attributes['service_catalog_item_id'] ?? null,
            'room_id' => $attributes['room_id'] ?? null,
            'title' => $attributes['title'] ?? 'Chamado com template',
            'description' => $attributes['description'] ?? 'Descricao do chamado.',
            'requester_id' => $requester->id,
            'assignee_id' => $attributes['assignee_id'] ?? null,
            'priority' => $attributes['priority'] ?? TicketPriority::MEDIUM,
            'resolved_at' => $attributes['resolved_at'] ?? null,
            'last_activity_at' => $attributes['last_activity_at'] ?? now(),
        ]);
    }

    private function htmlFragment(string $html, string $startNeedle, string $endNeedle): string
    {
        $start = strpos($html, $startNeedle);

        $this->assertNotFalse($start, "Unable to find [{$startNeedle}] in rendered HTML.");

        $end = strpos($html, $endNeedle, $start);

        $this->assertNotFalse($end, "Unable to find [{$endNeedle}] after [{$startNeedle}] in rendered HTML.");

        return substr($html, $start, $end - $start);
    }
}
