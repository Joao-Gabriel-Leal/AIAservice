<?php

namespace Tests\Feature\Search;

use App\Enums\AssetAllocationStatus;
use App\Enums\AssetStatus;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Search\Livewire\SearchPage;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Models\TicketMessage;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_groups_results_from_all_supported_sources(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Admin Lunar',
            'email' => 'admin.lunar@example.com',
        ]);

        $requester = User::factory()->create([
            'name' => 'Pessoa Lunar',
            'email' => 'pessoa.lunar@example.com',
            'role' => UserRole::REQUESTER,
            'sector_id' => $sector->id,
        ]);

        $technician = User::factory()->create([
            'name' => 'Tecnica Lunar',
            'email' => 'tecnica.lunar@example.com',
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $ticket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Falha lunar no VPN',
            'description' => 'Contexto lunar para validar a busca global.',
            'requester_id' => $requester->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now(),
        ]);

        TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $technician->id,
            'message' => 'Mensagem lunar registrada na conversa.',
            'is_system' => false,
        ]);

        ActivityLog::query()->create([
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
            'causer_id' => $technician->id,
            'sector_id' => $sector->id,
            'event' => 'ticket.updated',
            'description' => 'Historico lunar registrado para auditoria.',
            'properties' => ['sector_id' => $sector->id],
        ]);

        Asset::query()->create([
            'uuid' => (string) fake()->uuid(),
            'asset_code' => 'PAT-LUNAR-01',
            'name' => 'Notebook Lunar',
            'description' => 'Equipamento de teste da busca.',
            'serial_number' => 'SERIAL-LUNAR-777',
            'status' => AssetStatus::EM_USO,
            'allocation_status' => AssetAllocationStatus::ALLOCATED,
            'current_sector_id' => $sector->id,
            'current_room_id' => $room->id,
            'current_user_id' => $requester->id,
            'created_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('search', ['q' => 'lunar']));

        $response
            ->assertOk()
            ->assertSee('Chamados')
            ->assertSee('Mensagens')
            ->assertSee('Historico')
            ->assertSee('Usuarios')
            ->assertSee('Patrimonios')
            ->assertSee('Falha lunar no VPN')
            ->assertSee('Mensagem lunar registrada na conversa.')
            ->assertSee('Historico lunar registrado para auditoria.')
            ->assertSee('Pessoa Lunar')
            ->assertSee('Notebook Lunar');
    }

    public function test_global_search_can_filter_by_type_and_period_and_prioritizes_exact_ticket_id(): void
    {
        ['sector' => $sector, 'room' => $room, 'board' => $board, 'group' => $group, 'status' => $status] = $this->ticketContext();

        $technician = User::factory()->create([
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sector->id,
        ]);

        $exactTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Chamado principal',
            'description' => 'Descricao do chamado principal.',
            'requester_id' => $technician->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now(),
        ]);

        $secondaryTicket = Ticket::query()->create([
            'sector_id' => $sector->id,
            'ticket_board_id' => $board->id,
            'ticket_group_id' => $group->id,
            'ticket_status_id' => $status->id,
            'room_id' => $room->id,
            'title' => 'Item '.$exactTicket->id.' relacionado',
            'description' => 'Outro resultado numerico.',
            'requester_id' => $technician->id,
            'assignee_id' => $technician->id,
            'priority' => TicketPriority::LOW,
            'last_activity_at' => now()->subHour(),
        ]);

        TicketMessage::query()->create([
            'ticket_id' => $exactTicket->id,
            'user_id' => $technician->id,
            'message' => 'Mensagem filtrada antiga.',
            'is_system' => false,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        TicketMessage::query()->create([
            'ticket_id' => $exactTicket->id,
            'user_id' => $technician->id,
            'message' => 'Mensagem filtrada recente.',
            'is_system' => false,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($technician)->get(route('search', [
            'q' => (string) $exactTicket->id,
            'types' => ['tickets'],
        ]));

        $response
            ->assertOk()
            ->assertSeeInOrder([$exactTicket->title, $secondaryTicket->title]);

        Livewire::actingAs($technician)
            ->test(SearchPage::class)
            ->set('query', 'filtrada')
            ->set('types', ['messages'])
            ->set('dateFrom', now()->subDays(2)->toDateString())
            ->assertSee('Mensagens')
            ->assertSee('Mensagem filtrada recente.')
            ->assertDontSee('Chamados')
            ->assertDontSee('Mensagem filtrada antiga.');
    }

    public function test_global_search_hides_inaccessible_ticket_content_and_foreign_assets(): void
    {
        ['sector' => $sectorA, 'room' => $roomA] = $this->ticketContext('Operacoes', 'Empresa Busca A', 'Sala Busca A');
        ['sector' => $sectorB, 'room' => $roomB, 'board' => $boardB, 'group' => $groupB, 'status' => $statusB] = $this->ticketContext('Financeiro', 'Empresa Busca B', 'Sala Busca B');

        $requester = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $sectorA->id,
        ]);

        $otherUser = User::factory()->create([
            'name' => 'Analista Secreto',
            'email' => 'analista.secreto@example.com',
            'role' => UserRole::TECHNICIAN,
            'sector_id' => $sectorB->id,
        ]);

        $restrictedTicket = Ticket::query()->create([
            'sector_id' => $sectorB->id,
            'ticket_board_id' => $boardB->id,
            'ticket_group_id' => $groupB->id,
            'ticket_status_id' => $statusB->id,
            'room_id' => $roomB->id,
            'title' => 'Projeto secreto financeiro',
            'description' => 'Descricao secreta.',
            'requester_id' => $otherUser->id,
            'assignee_id' => $otherUser->id,
            'priority' => TicketPriority::HIGH,
            'last_activity_at' => now(),
        ]);

        TicketMessage::query()->create([
            'ticket_id' => $restrictedTicket->id,
            'user_id' => $otherUser->id,
            'message' => 'Mensagem secreta do financeiro.',
            'is_system' => false,
        ]);

        ActivityLog::query()->create([
            'subject_type' => Ticket::class,
            'subject_id' => $restrictedTicket->id,
            'causer_id' => $otherUser->id,
            'sector_id' => $sectorB->id,
            'event' => 'ticket.updated',
            'description' => 'Historico secreto do financeiro.',
            'properties' => ['sector_id' => $sectorB->id],
        ]);

        Asset::query()->create([
            'uuid' => (string) fake()->uuid(),
            'asset_code' => 'ASSET-SECRET-1',
            'name' => 'Notebook secreto',
            'serial_number' => 'SERIAL-SECRET-1',
            'status' => AssetStatus::EM_USO,
            'allocation_status' => AssetAllocationStatus::ALLOCATED,
            'current_sector_id' => $sectorB->id,
            'current_room_id' => $roomB->id,
            'current_user_id' => $otherUser->id,
            'created_by' => $otherUser->id,
        ]);

        Asset::query()->create([
            'uuid' => (string) fake()->uuid(),
            'asset_code' => 'ASSET-OWN-1',
            'name' => 'Notebook secreto pessoal',
            'serial_number' => 'SERIAL-SECRET-OWN',
            'status' => AssetStatus::EM_USO,
            'allocation_status' => AssetAllocationStatus::ALLOCATED,
            'current_sector_id' => $sectorA->id,
            'current_room_id' => $roomA->id,
            'current_user_id' => $requester->id,
            'created_by' => $requester->id,
        ]);

        $response = $this->actingAs($requester)->get(route('search', ['q' => 'secreto']));

        $response
            ->assertOk()
            ->assertDontSee('Projeto secreto financeiro')
            ->assertDontSee('Mensagem secreta do financeiro.')
            ->assertDontSee('Historico secreto do financeiro.')
            ->assertDontSee('Analista Secreto')
            ->assertDontSee('Notebook secreto')
            ->assertSee('Notebook secreto pessoal');
    }

    private function ticketContext(
        string $sectorName = 'Suporte',
        string $companyName = 'Empresa Busca',
        string $roomName = 'Sala Busca'
    ): array {
        $company = Company::query()->create([
            'name' => $companyName,
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => $sectorName,
            'slug' => str()->slug($companyName.'-'.$sectorName),
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => $roomName,
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return [
            'company' => $company,
            'sector' => $sector,
            'room' => $room,
            'board' => $board,
            'group' => $board->groups()->firstOrFail(),
            'status' => $board->statuses()->where('is_closed', false)->firstOrFail(),
        ];
    }
}
