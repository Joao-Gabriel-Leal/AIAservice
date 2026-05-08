<?php

namespace Tests\Feature\Management;

use App\Enums\AssetMovementType;
use App\Enums\AssetStatus;
use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Services\AssetMovementService;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_asset_with_initial_movement(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->post(route('assets.store'), [
            'name' => 'Notebook Dell',
            'description' => 'Equipamento principal',
            'serial_number' => 'ABC123',
            'brand' => 'Dell',
            'model' => 'Latitude',
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => $context['collaborator']->id,
        ]);

        $asset = Asset::query()->firstOrFail();

        $response->assertRedirect(route('assets.show', $asset, absolute: false));

        $this->assertSame('PAT-000001', $asset->asset_code);
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'name' => 'Notebook Dell',
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => $context['collaborator']->id,
        ]);

        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id,
            'type' => AssetMovementType::CADASTRO_INICIAL->value,
            'to_sector_id' => $context['sectorA']->id,
            'to_room_id' => $context['roomA']->id,
            'to_user_id' => $context['collaborator']->id,
            'to_status' => AssetStatus::EM_USO->value,
        ]);
    }

    public function test_super_admin_can_render_asset_management_pages(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $asset = $service->register([
            'name' => 'Switch gerenciavel',
            'description' => 'Rack principal',
            'serial_number' => 'SW-001',
            'brand' => 'Cisco',
            'model' => 'CBS250',
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => null,
        ], $admin);

        $this->actingAs($admin)
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSee('Patrimonios')
            ->assertSee($asset->asset_code);

        $this->actingAs($admin)
            ->get(route('assets.create'))
            ->assertOk()
            ->assertSee('Lotacao inicial')
            ->assertSee('Sala e obrigatoria e precisa estar cadastrada dentro do setor escolhido.')
            ->assertSee('Nenhuma sala disponivel para este setor.')
            ->assertSee('Cadastrar sala')
            ->assertSee('Gerenciar salas');

        $this->actingAs($admin)
            ->get(route('assets.movement.create', $asset))
            ->assertOk()
            ->assertSee('Movimentar patrimonio');
    }

    public function test_asset_creation_requires_room(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->from(route('assets.create'))->post(route('assets.store'), [
            'name' => 'Monitor sem sala',
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $context['sectorA']->id,
        ]);

        $response->assertRedirect(route('assets.create', absolute: false));
        $response->assertSessionHasErrors('current_room_id');

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_asset_creation_rejects_room_from_different_sector(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->from(route('assets.create'))->post(route('assets.store'), [
            'name' => 'Monitor',
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomB']->id,
        ]);

        $response->assertRedirect(route('assets.create', absolute: false));
        $response->assertSessionHasErrors('current_room_id');

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_asset_creation_rejects_duplicated_serial_number(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('assets.store'), [
            'name' => 'Notebook A',
            'serial_number' => 'SERIAL-001',
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
        ])->assertRedirect();

        $response = $this->actingAs($admin)->from(route('assets.create'))->post(route('assets.store'), [
            'name' => 'Notebook B',
            'serial_number' => 'SERIAL-001',
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
        ]);

        $response->assertRedirect(route('assets.create', absolute: false));
        $response->assertSessionHasErrors('serial_number');
    }

    public function test_super_admin_can_move_asset_between_sector_room_and_collaborator(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $asset = $service->register([
            'name' => 'Projetor',
            'description' => 'Sala de reuniao',
            'serial_number' => null,
            'brand' => 'Epson',
            'model' => 'X1',
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => null,
        ], $admin);

        $response = $this->actingAs($admin)->post(route('assets.movement.store', $asset), [
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $context['sectorB']->id,
            'current_room_id' => $context['roomB']->id,
            'current_user_id' => $context['collaboratorB']->id,
            'reason' => 'Entrega para novo setor',
            'notes' => 'Instalado na sala de treinamento',
        ]);

        $response->assertRedirect(route('assets.show', $asset, absolute: false));

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $context['sectorB']->id,
            'current_room_id' => $context['roomB']->id,
            'current_user_id' => $context['collaboratorB']->id,
        ]);

        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id,
            'type' => AssetMovementType::TRANSFERENCIA_LOCAL->value,
            'from_sector_id' => $context['sectorA']->id,
            'from_room_id' => $context['roomA']->id,
            'to_sector_id' => $context['sectorB']->id,
            'to_room_id' => $context['roomB']->id,
            'to_user_id' => $context['collaboratorB']->id,
            'to_status' => AssetStatus::EM_USO->value,
        ]);
    }

    public function test_status_change_and_devolution_keep_history_and_audit(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $asset = $service->register([
            'name' => 'Impressora',
            'description' => null,
            'serial_number' => 'PRN-100',
            'brand' => 'HP',
            'model' => 'LaserJet',
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => $context['collaborator']->id,
        ], $admin);

        $this->actingAs($admin)->post(route('assets.movement.store', $asset), [
            'status' => AssetStatus::MANUTENCAO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => $context['collaborator']->id,
            'reason' => 'Falha no fusor',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('assets.movement.store', $asset), [
            'status' => AssetStatus::MANUTENCAO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => null,
            'reason' => 'Equipamento recolhido',
        ])->assertRedirect();

        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id,
            'type' => AssetMovementType::MUDANCA_STATUS->value,
            'to_status' => AssetStatus::MANUTENCAO->value,
        ]);

        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id,
            'type' => AssetMovementType::DEVOLUCAO_COLABORADOR->value,
            'to_user_id' => null,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Asset::class,
            'subject_id' => $asset->id,
            'event' => 'asset.movement.created',
        ]);
    }

    public function test_baixado_asset_cannot_receive_new_movements(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $asset = $service->register([
            'name' => 'Desktop antigo',
            'description' => null,
            'serial_number' => 'LOW-100',
            'brand' => 'HP',
            'model' => 'ProDesk',
            'status' => AssetStatus::BAIXADO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => null,
        ], $admin);

        $response = $this->actingAs($admin)->from(route('assets.movement.create', $asset))->post(route('assets.movement.store', $asset), [
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => null,
            'reason' => 'Tentativa de reativacao',
        ]);

        $response->assertRedirect(route('assets.movement.create', $asset, absolute: false));
        $response->assertSessionHasErrors('status');
    }

    public function test_regular_users_cannot_access_asset_management_but_can_open_their_own_asset(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $asset = $service->register([
            'name' => 'Mouse corporativo',
            'description' => null,
            'serial_number' => null,
            'brand' => 'Logitech',
            'model' => 'M90',
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => $context['collaborator']->id,
        ], $admin);

        $this->actingAs($context['collaborator'])
            ->get(route('assets.index'))
            ->assertForbidden();

        $this->actingAs($context['collaborator'])
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee($asset->asset_code)
            ->assertSee($asset->qrCodeUrl(), false);

        $this->actingAs($context['collaboratorB'])
            ->get(route('assets.show', $asset))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_when_opening_the_asset_qr_page(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $asset = $service->register([
            'name' => 'Notebook de campo',
            'description' => 'Uso em visitas externas',
            'serial_number' => 'QR-100',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad',
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => $context['collaborator']->id,
        ], $admin);

        $this->get(route('assets.public.show', $asset))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_user_can_open_asset_qr_page_with_main_data(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $asset = $service->register([
            'name' => 'Notebook de campo',
            'description' => 'Uso em visitas externas',
            'serial_number' => 'QR-100',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad',
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => $context['collaborator']->id,
        ], $admin);

        $this->actingAs($admin)
            ->get(route('assets.public.show', $asset))
            ->assertOk()
            ->assertSee('Dados principais do patrimonio')
            ->assertSee($asset->asset_code)
            ->assertSee($asset->name)
            ->assertSee($context['sectorA']->name)
            ->assertSee($context['roomA']->name)
            ->assertSee($context['collaborator']->name);
    }

    public function test_asset_qr_uses_current_request_host_on_management_pages(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $asset = $service->register([
            'name' => 'Tablet externo',
            'description' => null,
            'serial_number' => 'QR-HOST',
            'brand' => 'Samsung',
            'model' => 'Tab',
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => null,
        ], $admin);

        $expectedUrl = 'https://homolog-chamados.anadem.com.br'.route('assets.public.show', $asset, absolute: false);

        $this->actingAs($admin)
            ->get('https://homolog-chamados.anadem.com.br'.route('assets.show', $asset, absolute: false))
            ->assertOk()
            ->assertSee($expectedUrl, false);
    }

    public function test_profile_shows_only_assets_assigned_to_authenticated_user(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $ownedAsset = $service->register([
            'name' => 'Notebook pessoal',
            'description' => null,
            'serial_number' => null,
            'brand' => 'Dell',
            'model' => 'Vostro',
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => $context['collaborator']->id,
        ], $admin);

        $otherAsset = $service->register([
            'name' => 'Headset reserva',
            'description' => null,
            'serial_number' => null,
            'brand' => 'Jabra',
            'model' => 'Evolve',
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $context['sectorB']->id,
            'current_room_id' => $context['roomB']->id,
            'current_user_id' => $context['collaboratorB']->id,
        ], $admin);

        $this->actingAs($context['collaborator'])
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Patrimonios vinculados')
            ->assertSee($ownedAsset->asset_code)
            ->assertSee($ownedAsset->qrCodeUrl(), false)
            ->assertDontSee($otherAsset->asset_code);
    }

    public function test_asset_index_filters_by_status_and_collaborator(): void
    {
        $context = $this->assetContext();
        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $firstAsset = $service->register([
            'name' => 'Notebook filtro',
            'description' => null,
            'serial_number' => 'FLT-001',
            'brand' => 'Dell',
            'model' => 'Latitude',
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => $context['collaborator']->id,
        ], $admin);

        $secondAsset = $service->register([
            'name' => 'Monitor filtro',
            'description' => null,
            'serial_number' => 'FLT-002',
            'brand' => 'LG',
            'model' => 'UltraWide',
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $context['sectorB']->id,
            'current_room_id' => $context['roomB']->id,
            'current_user_id' => null,
        ], $admin);

        $this->actingAs($admin)
            ->get(route('assets.index', [
                'status' => AssetStatus::EM_USO->value,
                'user_id' => $context['collaborator']->id,
            ]))
            ->assertOk()
            ->assertSee($firstAsset->asset_code)
            ->assertDontSee($secondAsset->asset_code);
    }

    public function test_asset_index_renders_collaborator_as_avatar_only_reference(): void
    {
        $context = $this->assetContext();
        $context['collaborator']->forceFill(['name' => 'Clara Lima'])->save();

        $admin = User::factory()->superAdmin()->create();
        $service = app(AssetMovementService::class);

        $assignedAsset = $service->register([
            'name' => 'Notebook Avatar',
            'description' => 'Ativo com colaborador.',
            'serial_number' => 'NB-AVATAR-01',
            'brand' => 'Dell',
            'model' => 'Latitude',
            'status' => AssetStatus::EM_USO->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => $context['collaborator']->id,
        ], $admin);

        $unassignedAsset = $service->register([
            'name' => 'Monitor Livre',
            'description' => 'Ativo sem colaborador.',
            'serial_number' => 'MN-LIVRE-01',
            'brand' => 'LG',
            'model' => 'UltraWide',
            'status' => AssetStatus::DISPONIVEL->value,
            'current_sector_id' => $context['sectorA']->id,
            'current_room_id' => $context['roomA']->id,
            'current_user_id' => null,
        ], $admin);

        $response = $this->actingAs($admin)->get(route('assets.index'));

        $response->assertOk();
        $response->assertSee($assignedAsset->asset_code);
        $response->assertSee($unassignedAsset->asset_code);
        $response->assertSee('title="'.$context['collaborator']->name.'"', false);
        $response->assertSee('Nao vinculado');
        $response->assertDontSee('<td class="px-6 py-4 text-slate-600">'.$context['collaborator']->name.'</td>', false);
        $response->assertDontSee('<td class="px-6 py-4 text-slate-600">Nao vinculado</td>', false);
    }

    private function assetContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Patrimonio',
            'is_active' => true,
        ]);

        $sectorA = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Tecnologia',
            'slug' => 'tecnologia',
            'is_active' => true,
        ]);

        $sectorB = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'Financeiro',
            'slug' => 'financeiro',
            'is_active' => true,
        ]);

        $roomA = Room::query()->create([
            'sector_id' => $sectorA->id,
            'name' => 'Sala TI',
            'is_active' => true,
        ]);

        $roomB = Room::query()->create([
            'sector_id' => $sectorB->id,
            'name' => 'Sala Financeiro',
            'is_active' => true,
        ]);

        $collaborator = User::factory()->create([
            'sector_id' => $sectorA->id,
            'room_id' => $roomA->id,
        ]);

        $collaboratorB = User::factory()->create([
            'sector_id' => $sectorB->id,
            'room_id' => $roomB->id,
        ]);

        return compact('company', 'sectorA', 'sectorB', 'roomA', 'roomB', 'collaborator', 'collaboratorB');
    }
}
