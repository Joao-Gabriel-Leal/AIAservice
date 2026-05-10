<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Shared\Support\CurrentCompanyContext;
use App\Modules\Tickets\Models\Ticket;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCompanyContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_between_accessible_companies_and_preference_is_saved(): void
    {
        $first = $this->companyContext('Aiatec Atual');
        $second = $this->companyContext('Anadem Atual');
        $blocked = $this->companyContext('Dubbox Sem Acesso');

        $user = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $first['sector']->id,
            'room_id' => $first['room']->id,
            'current_company_id' => $first['company']->id,
        ]);
        $user->sectorAccesses()->updateOrCreate(
            ['sector_id' => $second['sector']->id],
            ['access_level' => 'requester'],
        );

        $this->actingAs($user)
            ->post(route('context.company.store'), ['company_id' => $second['company']->id])
            ->assertRedirect();

        $this->assertSame($second['company']->id, $user->fresh()->current_company_id);

        $this->actingAs($user)
            ->post(route('context.company.store'), ['company_id' => $blocked['company']->id])
            ->assertForbidden();

        $this->assertSame($second['company']->id, $user->fresh()->current_company_id);
    }

    public function test_invalid_saved_company_falls_back_to_first_accessible_company(): void
    {
        $first = $this->companyContext('Aiatec Fallback');
        $second = $this->companyContext('Anadem Fallback');

        $user = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $first['sector']->id,
            'room_id' => $first['room']->id,
            'current_company_id' => $second['company']->id,
        ]);

        $current = app(CurrentCompanyContext::class)->current($user);

        $this->assertTrue($current->is($first['company']));
        $this->assertSame($first['company']->id, $user->fresh()->current_company_id);
    }

    public function test_dashboard_uses_current_company_scope_for_shared_users(): void
    {
        $aiatec = $this->companyContext('Aiatec Dashboard');
        $anadem = $this->companyContext('Anadem Dashboard');

        $user = User::factory()->create([
            'role' => UserRole::REQUESTER,
            'sector_id' => $aiatec['sector']->id,
            'room_id' => $aiatec['room']->id,
            'current_company_id' => $aiatec['company']->id,
        ]);
        $user->sectorAccesses()->updateOrCreate(
            ['sector_id' => $anadem['sector']->id],
            ['access_level' => 'requester'],
        );

        $this->ticket($aiatec, $user, 'Chamado Aiatec Visivel');
        $this->ticket($anadem, $user, 'Chamado Anadem Oculto');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Chamado Aiatec Visivel')
            ->assertDontSeeText('Chamado Anadem Oculto');

        $this->actingAs($user)
            ->post(route('context.company.store'), ['company_id' => $anadem['company']->id])
            ->assertRedirect();

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Chamado Anadem Oculto')
            ->assertDontSeeText('Chamado Aiatec Visivel');
    }

    public function test_direct_board_link_switches_to_accessible_company(): void
    {
        $first = $this->companyContext('Aiatec Quadros');
        $second = $this->companyContext('Dubbox Quadros');
        $user = User::factory()->superAdmin()->create([
            'current_company_id' => $first['company']->id,
        ]);

        $this->actingAs($user)
            ->get(route('tickets.board.show', $second['board']))
            ->assertOk();

        $this->assertSame($second['company']->id, $user->fresh()->current_company_id);
    }

    private function companyContext(string $companyName): array
    {
        $company = Company::query()->create([
            'name' => $companyName,
            'is_active' => true,
        ]);

        $sector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => $companyName.' Setor',
            'slug' => str($companyName)->slug()->toString(),
            'is_active' => true,
        ]);

        $room = Room::query()->create([
            'sector_id' => $sector->id,
            'name' => $companyName.' Sala',
            'is_active' => true,
        ]);

        $board = app(SectorProvisioningService::class)->provision($sector);

        return compact('company', 'sector', 'room', 'board');
    }

    private function ticket(array $context, User $requester, string $title): Ticket
    {
        return Ticket::query()->create([
            'sector_id' => $context['sector']->id,
            'ticket_board_id' => $context['board']->id,
            'ticket_group_id' => $context['board']->groups()->firstOrFail()->id,
            'ticket_status_id' => $context['board']->statuses()->where('is_closed', false)->firstOrFail()->id,
            'room_id' => $context['room']->id,
            'title' => $title,
            'description' => 'Chamado para validar contexto de empresa.',
            'requester_id' => $requester->id,
            'priority' => TicketPriority::MEDIUM,
            'last_activity_at' => now(),
        ]);
    }
}
