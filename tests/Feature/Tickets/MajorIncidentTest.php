<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Livewire\ShowPage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketGroup;
use App\Modules\Tickets\Models\TicketStatus;
use App\Modules\Tickets\Services\MajorIncidentService;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MajorIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_mark_ticket_as_major_incident_and_link_children(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext('Incidente Link');

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $incident = $this->ticketFor($board, $group, $status, $requester, 'Internet caiu na matriz');
        $child = $this->ticketFor($board, $group, $status, $requester, 'Internet fora na matriz');

        Livewire::actingAs($operator)
            ->test(ShowPage::class, ['ticket' => $incident])
            ->call('toggleMajorIncident')
            ->assertHasNoErrors()
            ->assertSee('Este chamado e o incidente principal.');

        app(MajorIncidentService::class)->attachChild($operator, $incident->fresh(), $child);

        $this->assertDatabaseHas('tickets', [
            'id' => $incident->id,
            'is_major_incident' => true,
        ]);
        $this->assertDatabaseHas('tickets', [
            'id' => $child->id,
            'major_incident_ticket_id' => $incident->id,
        ]);

        Livewire::actingAs($operator)
            ->test(ShowPage::class, ['ticket' => $incident->fresh()])
            ->assertSee('Chamados vinculados')
            ->assertSee($child->title);
    }

    public function test_major_incident_blocks_invalid_child_links(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext('Incidente Bloqueios');
        ['sector' => $outsideSector, 'board' => $outsideBoard, 'group' => $outsideGroup, 'status' => $outsideStatus] = $this->ticketContext('Incidente Fora');

        $operator = User::factory()->superAdmin()->create();
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $outsideRequester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $outsideSector->id,
        ]);

        $service = app(MajorIncidentService::class);
        $incident = $this->ticketFor($board, $group, $status, $requester, 'Queda de rede geral');
        $service->markAsMajorIncident($operator, $incident);

        $outsideTicket = $this->ticketFor($outsideBoard, $outsideGroup, $outsideStatus, $outsideRequester, 'Queda de rede geral fora');
        $closedTicket = $this->ticketFor($board, $closedGroup, $closedStatus, $requester, 'Queda de rede encerrada', null, true);
        $otherMajor = $this->ticketFor($board, $group, $status, $requester, 'Outro incidente principal');
        $service->markAsMajorIncident($operator, $otherMajor);

        $this->assertValidationFailure(
            fn () => $service->attachChild($operator, $incident->fresh(), $outsideTicket),
            'mesmo quadro',
        );
        $this->assertValidationFailure(
            fn () => $service->attachChild($operator, $incident->fresh(), $closedTicket),
            'encerrado',
        );
        $this->assertValidationFailure(
            fn () => $service->attachChild($operator, $incident->fresh(), $incident->fresh()),
            'ele mesmo',
        );
        $this->assertValidationFailure(
            fn () => $service->attachChild($operator, $incident->fresh(), $otherMajor->fresh()),
            'incidente principal',
        );
    }

    public function test_suggestions_are_recent_open_same_board_and_title_related(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status, 'closedGroup' => $closedGroup, 'closedStatus' => $closedStatus] = $this->ticketContext('Incidente Sugestoes');
        ['sector' => $outsideSector, 'board' => $outsideBoard, 'group' => $outsideGroup, 'status' => $outsideStatus] = $this->ticketContext('Incidente Sugestoes Fora');

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $outsideRequester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $outsideSector->id,
        ]);

        $source = $this->ticketFor($board, $group, $status, $requester, 'Internet caiu no escritorio');
        $matching = $this->ticketFor($board, $group, $status, $requester, 'Internet sem acesso no escritorio');
        $unrelated = $this->ticketFor($board, $group, $status, $requester, 'Impressora sem toner');
        $closed = $this->ticketFor($board, $closedGroup, $closedStatus, $requester, 'Internet caiu encerrada', null, true);
        $stale = $this->ticketFor($board, $group, $status, $requester, 'Internet caiu ontem');
        $stale->forceFill(['updated_at' => now()->subHours(13)])->save();
        $outside = $this->ticketFor($outsideBoard, $outsideGroup, $outsideStatus, $outsideRequester, 'Internet caiu no escritorio');

        $suggestions = app(MajorIncidentService::class)->suggestions($operator, $source);

        $this->assertTrue($suggestions->contains('id', $matching->id));
        $this->assertFalse($suggestions->contains('id', $unrelated->id));
        $this->assertFalse($suggestions->contains('id', $closed->id));
        $this->assertFalse($suggestions->contains('id', $stale->id));
        $this->assertFalse($suggestions->contains('id', $outside->id));
    }

    public function test_bulk_message_is_replicated_only_to_selected_children(): void
    {
        Notification::fake();

        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext('Incidente Mensagem');

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $service = app(MajorIncidentService::class);
        $incident = $this->ticketFor($board, $group, $status, $requester, 'Internet caiu no predio');
        $childA = $this->ticketFor($board, $group, $status, $requester, 'Internet fora no terceiro andar');
        $childB = $this->ticketFor($board, $group, $status, $requester, 'Internet lenta no segundo andar');

        $service->attachChild($operator, $incident, $childA);
        $service->attachChild($operator, $incident->fresh(), $childB);

        Livewire::actingAs($operator)
            ->test(ShowPage::class, ['ticket' => $incident->fresh()])
            ->set('incidentBulkMessage', 'Fornecedor acionado para normalizacao do link.')
            ->set('incidentSelectedChildIds', [(string) $childA->id])
            ->call('sendIncidentBulkMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $incident->id,
            'message' => 'Fornecedor acionado para normalizacao do link.',
        ]);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $childA->id,
            'message' => 'Fornecedor acionado para normalizacao do link.',
        ]);
        $this->assertDatabaseMissing('ticket_messages', [
            'ticket_id' => $childB->id,
            'message' => 'Fornecedor acionado para normalizacao do link.',
        ]);
    }

    public function test_bulk_close_resolves_only_selected_children(): void
    {
        Notification::fake();

        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext('Incidente Fechamento');

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $service = app(MajorIncidentService::class);
        $incident = $this->ticketFor($board, $group, $status, $requester, 'Internet indisponivel');
        $childA = $this->ticketFor($board, $group, $status, $requester, 'Internet indisponivel sala A');
        $childB = $this->ticketFor($board, $group, $status, $requester, 'Internet indisponivel sala B');

        $service->attachChild($operator, $incident, $childA);
        $service->attachChild($operator, $incident->fresh(), $childB);

        Livewire::actingAs($operator)
            ->test(ShowPage::class, ['ticket' => $incident->fresh()])
            ->set('incidentResolutionMessage', 'Link restabelecido e acesso normalizado.')
            ->set('incidentSelectedChildIds', [(string) $childA->id])
            ->call('closeIncidentChildren')
            ->assertHasNoErrors();

        $this->assertTrue($childA->fresh(['group', 'status'])->isClosed());
        $this->assertFalse($childB->fresh(['group', 'status'])->isClosed());
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $childA->id,
            'message' => 'Link restabelecido e acesso normalizado.',
        ]);
        $this->assertDatabaseMissing('ticket_messages', [
            'ticket_id' => $childB->id,
            'message' => 'Link restabelecido e acesso normalizado.',
        ]);
    }

    public function test_requester_does_not_see_major_incident_operator_panel(): void
    {
        ['sector' => $sector, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext('Incidente Solicitante');

        $operator = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);
        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);
        $incident = $this->ticketFor($board, $group, $status, $requester, 'Internet caiu para solicitante');

        app(MajorIncidentService::class)->markAsMajorIncident($operator, $incident);

        Livewire::actingAs($requester)
            ->test(ShowPage::class, ['ticket' => $incident->fresh()])
            ->assertDontSee('Agrupamento operacional')
            ->assertDontSee('Marcar incidente')
            ->assertDontSee('Fechar chamados selecionados');
    }

    private function ticketContext(string $sectorName): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa '.$sectorName,
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
            'name' => 'Sala '.$sectorName,
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'group' => $board->groups()->where('is_closed', false)->orderBy('sort_order')->firstOrFail(),
            'closedGroup' => $board->groups()->where('is_closed', true)->orderBy('sort_order')->firstOrFail(),
            'status' => $board->statuses()->where('is_closed', false)->orderBy('sort_order')->firstOrFail(),
            'closedStatus' => $board->statuses()->where('is_closed', true)->orderBy('sort_order')->firstOrFail(),
        ];
    }

    private function ticketFor(
        TicketBoard $board,
        TicketGroup $group,
        TicketStatus $status,
        User $requester,
        string $title,
        ?User $assignee = null,
        bool $closed = false,
    ): Ticket {
        return Ticket::query()->create([
            'sector_id' => $board->sector_id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $requester->room_id,
            'title' => $title,
            'description' => 'Chamado usado nos testes de incidente massivo.',
            'requester_id' => $requester->id,
            'assignee_id' => $assignee?->id,
            'priority' => TicketPriority::MEDIUM,
            'resolved_at' => $closed ? now() : null,
            'last_activity_at' => now(),
        ]);
    }

    private function assertValidationFailure(callable $callback, string $expectedMessagePart): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $messages = collect($exception->errors())->flatten()->implode(' ');

            $this->assertStringContainsString($expectedMessagePart, $messages);

            return;
        }

        $this->fail('A validacao esperada nao foi disparada.');
    }
}
