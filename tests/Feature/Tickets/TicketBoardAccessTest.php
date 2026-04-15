<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketBoardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_empty_board_state_when_no_active_sector_exists(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('tickets.board'))
            ->assertOk()
            ->assertSeeText('Crie um setor para comecar a organizar o quadro de chamados.');
    }

    public function test_super_admin_sees_empty_settings_state_when_no_active_sector_exists(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->get(route('tickets.settings'))
            ->assertOk()
            ->assertSeeText('Nenhum quadro disponivel para configuracao.');
    }

    public function test_user_without_operational_access_still_gets_forbidden_when_active_sector_exists(): void
    {
        $user = User::factory()->create();

        $company = Company::query()->create([
            'name' => 'Empresa Sem Acesso',
            'is_active' => true,
        ]);

        Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Operacoes',
            'slug' => 'operacoes',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('tickets.board'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('tickets.settings'))
            ->assertForbidden();
    }
}
