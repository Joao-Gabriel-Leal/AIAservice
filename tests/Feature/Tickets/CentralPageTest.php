<?php

namespace Tests\Feature\Tickets;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Services\SectorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_page_selects_the_requested_sector_from_the_query_string(): void
    {
        ['firstSector' => $firstSector, 'secondSector' => $secondSector] = $this->centralContext();

        $this->actingAs(User::factory()->create())
            ->get(route('tickets.central', ['sector' => $secondSector->id]))
            ->assertOk()
            ->assertSeeText("Formularios de {$secondSector->name}")
            ->assertSeeText($firstSector->name);
    }

    public function test_invalid_sector_query_falls_back_to_the_first_active_sector(): void
    {
        ['firstSector' => $firstSector] = $this->centralContext();

        $this->actingAs(User::factory()->create())
            ->get(route('tickets.central', ['sector' => 99999]))
            ->assertOk()
            ->assertSeeText("Formularios de {$firstSector->name}");
    }

    public function test_selected_sector_without_catalog_items_shows_empty_state(): void
    {
        ['secondSector' => $secondSector] = $this->centralContext();

        $secondSector->board->catalogItems()->update(['is_active' => false]);

        $this->actingAs(User::factory()->create())
            ->get(route('tickets.central', ['sector' => $secondSector->id]))
            ->assertOk()
            ->assertSeeText('Este setor ainda nao publicou formularios ativos no catalogo.')
            ->assertDontSeeText('Abrir chamado geral');
    }

    public function test_central_page_does_not_offer_a_general_ticket_shortcut(): void
    {
        $this->centralContext();

        $this->actingAs(User::factory()->create())
            ->get(route('tickets.central'))
            ->assertOk()
            ->assertDontSeeText('Abrir chamado geral')
            ->assertDontSeeText('O chamado geral continua disponivel');
    }

    private function centralContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Central',
            'is_active' => true,
        ]);

        $firstSector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Financeiro',
            'slug' => 'financeiro',
            'description' => 'Solicitacoes financeiras.',
            'is_active' => true,
        ]);

        $secondSector = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'TI',
            'slug' => 'ti',
            'description' => 'Solicitacoes de tecnologia.',
            'is_active' => true,
        ]);

        app(SectorProvisioningService::class)->provision($firstSector);
        app(SectorProvisioningService::class)->provision($secondSector);

        $secondSector->load('board.catalogItems');

        return [
            'company' => $company,
            'firstSector' => $firstSector,
            'secondSector' => $secondSector,
        ];
    }
}
