<?php

namespace Tests\Feature\Management;

use App\Enums\AssetAllocationStatus;
use App\Enums\AssetStatus;
use App\Models\User;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\AssetImportBatch;
use App\Modules\Assets\Models\AssetImportRow;
use App\Modules\Companies\Models\Company;
use App\Modules\Rooms\Models\Room;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AssetImportCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    public function test_import_command_stages_two_thousand_rows_and_promotes_only_ready_assets(): void
    {
        $this->assetImportContext();

        $workbookPath = $this->createWorkbook($this->largeWorkbookRows());

        $this->artisan('assets:import-anadem', [
            'path' => $workbookPath,
        ])->assertSuccessful();

        $batch = AssetImportBatch::query()->latest('id')->firstOrFail();

        $this->assertSame(2000, $batch->total_rows);
        $this->assertSame(1050, $batch->ready_rows);
        $this->assertSame(950, $batch->pending_review_rows);
        $this->assertSame(0, $batch->error_rows);
        $this->assertSame(1050, $batch->promoted_rows);
        $this->assertSame(2000, $batch->metadata['unique_asset_codes']);

        $this->assertDatabaseCount('asset_import_rows', 2000);
        $this->assertDatabaseCount('assets', 1050);
        $this->assertDatabaseCount('asset_financial_profiles', 1050);

        $pendingRow = AssetImportRow::query()->where('asset_code', 'PLACA-0004')->firstOrFail();

        $this->assertSame('pending_review', $pendingRow->processing_status);
        $this->assertNull($pendingRow->asset_id);

        $availableAsset = Asset::query()->with('financialProfile', 'currentSector', 'currentRoom')->where('asset_code', 'PLACA-0001')->firstOrFail();
        $inUseAsset = Asset::query()->where('asset_code', 'PLACA-0002')->firstOrFail();
        $unmatchedAsset = Asset::query()->where('asset_code', 'PLACA-0003')->firstOrFail();

        $this->assertSame(AssetStatus::DISPONIVEL, $availableAsset->status);
        $this->assertSame(AssetAllocationStatus::ALLOCATED, $availableAsset->allocation_status);
        $this->assertSame('TI', $availableAsset->currentSector?->name);
        $this->assertSame('Infraestrutura', $availableAsset->currentRoom?->name);
        $this->assertSame(60, $availableAsset->financialProfile?->useful_life_months);
        $this->assertSame('2026-04-15', $availableAsset->financialProfile?->registered_at?->toDateString());

        $this->assertSame(AssetStatus::EM_USO, $inUseAsset->status);

        $this->assertSame(AssetStatus::BAIXADO, $unmatchedAsset->status);
        $this->assertSame(AssetAllocationStatus::PENDING_REVIEW, $unmatchedAsset->allocation_status);
        $this->assertNull($unmatchedAsset->current_sector_id);
        $this->assertNull($unmatchedAsset->current_room_id);
    }

    public function test_import_command_upserts_by_asset_code_without_duplication(): void
    {
        $this->assetImportContext();

        $firstWorkbook = $this->createWorkbook([
            [
                'plate' => 'PLACA-9001',
                'item' => 'Notebook legado',
                'category' => '04_AN_Equip. de proc',
                'description' => 'https://anadem.test/notebook',
                'status' => 'ATIVO',
                'collaborator' => 'Uso Livre',
                'sector' => 'TI',
                'observation' => 'Primeira carga',
                'invoice' => 'NF-9001',
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-10')),
                'value' => 5000.00,
                'useful_life' => null,
                'remaining_life' => 55,
                'depreciation' => 500.00,
            ],
        ]);

        $secondWorkbook = $this->createWorkbook([
            [
                'plate' => 'PLACA-9001',
                'item' => 'Notebook legado atualizado',
                'category' => '04_AN_Equip. de proc',
                'description' => 'https://anadem.test/notebook-v2',
                'status' => 'ATIVO',
                'collaborator' => 'Carlos Demo',
                'sector' => 'TI',
                'observation' => 'Carga revisada',
                'invoice' => 'NF-9001-B',
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-11')),
                'value' => 6500.00,
                'useful_life' => null,
                'remaining_life' => 54,
                'depreciation' => 650.00,
            ],
        ]);

        $this->artisan('assets:import-anadem', ['path' => $firstWorkbook])->assertSuccessful();
        $this->artisan('assets:import-anadem', ['path' => $secondWorkbook])->assertSuccessful();

        $asset = Asset::query()->with('financialProfile')->where('asset_code', 'PLACA-9001')->firstOrFail();

        $this->assertDatabaseCount('assets', 1);
        $this->assertSame('Notebook legado atualizado', $asset->name);
        $this->assertSame(AssetStatus::EM_USO, $asset->status);
        $this->assertSame('Carga revisada', $asset->description);
        $this->assertSame('NF-9001-B', $asset->financialProfile?->invoice_number);
    }

    public function test_asset_index_and_detail_show_patrimonial_import_context(): void
    {
        $this->assetImportContext();

        $workbookPath = $this->createWorkbook([
            [
                'plate' => 'PLACA-5001',
                'item' => 'Notebook patrimonial',
                'category' => '04_AN_Equip. de proc',
                'description' => 'https://anadem.test/item',
                'status' => 'ATIVO',
                'collaborator' => 'Uso de Todos do Setor',
                'sector' => 'TI',
                'observation' => 'Notebook da diretoria',
                'invoice' => 'NF-5001',
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-12')),
                'value' => 4100.20,
                'useful_life' => null,
                'remaining_life' => 40,
                'depreciation' => 820.00,
            ],
            [
                'plate' => 'PLACA-5002',
                'item' => 'Item sem match',
                'category' => '05_AN_MÓVEIS E UTENS',
                'description' => '',
                'status' => 'BAIXADO',
                'collaborator' => '',
                'sector' => 'Centro de Eventos Anadem',
                'observation' => 'Sem setor homologado',
                'invoice' => 'NF-5002',
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-13')),
                'value' => 1200.00,
                'useful_life' => 36,
                'remaining_life' => 0,
                'depreciation' => 1200.00,
            ],
            [
                'plate' => 'PLACA-5003',
                'item' => 'Linha sem status',
                'category' => '04_AN_Equip. de proc',
                'description' => '',
                'status' => '',
                'collaborator' => '',
                'sector' => 'FINANCEIRO',
                'observation' => 'Aguardando saneamento',
                'invoice' => '',
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-14')),
                'value' => 900.00,
                'useful_life' => null,
                'remaining_life' => 12,
                'depreciation' => 100.00,
            ],
        ]);

        $this->artisan('assets:import-anadem', ['path' => $workbookPath])->assertSuccessful();

        $admin = User::factory()->superAdmin()->create();
        $pendingAsset = Asset::query()->where('asset_code', 'PLACA-5002')->firstOrFail();
        $availableAsset = Asset::query()->where('asset_code', 'PLACA-5001')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('assets.index', [
                'allocation_status' => AssetAllocationStatus::PENDING_REVIEW->value,
            ]))
            ->assertOk()
            ->assertSee('Ultima importacao patrimonial')
            ->assertSee('Linhas pendentes de saneamento')
            ->assertSee('PLACA-5002')
            ->assertSee('Pendente de saneamento')
            ->assertDontSee('PLACA-5001');

        $this->actingAs($admin)
            ->get(route('assets.show', $availableAsset))
            ->assertOk()
            ->assertSee('Dados patrimoniais')
            ->assertSee('NF-5001')
            ->assertSee('Origem')
            ->assertSee('Notebook da diretoria');

        $this->actingAs($admin)
            ->get(route('assets.show', $pendingAsset))
            ->assertOk()
            ->assertSee('Sem setor')
            ->assertSee('Pendente de saneamento');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    private function assetImportContext(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa Patrimonio',
            'is_active' => true,
        ]);

        $ti = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'TI',
            'slug' => 'ti',
            'color' => '#3D567B',
            'is_active' => true,
        ]);

        $compras = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'COMPRAS',
            'slug' => 'compras',
            'color' => '#5B8E7D',
            'is_active' => true,
        ]);

        $financeiro = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'FINANCEIRO',
            'slug' => 'financeiro',
            'color' => '#8A5A44',
            'is_active' => true,
        ]);

        $juridico = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'JURÍDICO',
            'slug' => 'juridico',
            'color' => '#725E39',
            'is_active' => true,
        ]);

        $comercial = Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'COMERCIAL',
            'slug' => 'comercial',
            'color' => '#4E7AA8',
            'is_active' => true,
        ]);

        Room::query()->create(['sector_id' => $ti->id, 'name' => 'Infraestrutura', 'is_active' => true]);
        Room::query()->create(['sector_id' => $compras->id, 'name' => 'Homologacao', 'is_active' => true]);
        Room::query()->create(['sector_id' => $financeiro->id, 'name' => 'Tesouraria', 'is_active' => true]);
        Room::query()->create(['sector_id' => $juridico->id, 'name' => 'Contratos', 'is_active' => true]);
        Room::query()->create(['sector_id' => $comercial->id, 'name' => 'Operacao', 'is_active' => true]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function createWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $referenceSheet = $spreadsheet->getActiveSheet();
        $referenceSheet->setTitle('BASE DE DADOS');
        $referenceSheet->setCellValue('B2', 'CATEGORIA');
        $referenceSheet->setCellValue('C2', 'VIDA ÚTIL (MESES)');
        $referenceSheet->setCellValue('B3', '04_AN_Equip. de proc');
        $referenceSheet->setCellValue('C3', 60);
        $referenceSheet->setCellValue('B4', '05_AN_MÓVEIS E UTENS');
        $referenceSheet->setCellValue('C4', 360);
        $referenceSheet->setCellValue('B5', '03_AN_Máquinas e Eq');
        $referenceSheet->setCellValue('C5', 60);

        $sourceSheet = $spreadsheet->createSheet();
        $sourceSheet->setTitle('Anadem');

        $headers = [
            'B3' => 'Nº - PLACA',
            'C3' => 'EMPRESA',
            'D3' => 'ITEM',
            'E3' => 'CATEGORIA',
            'F3' => 'DESCRIÇÃO',
            'G3' => 'STATUS',
            'H3' => 'POSICIONAMENTO ATUAL (COLABORADOR(A))',
            'I3' => 'POSICIONAMENTO ATUAL (SETOR / DEPARTAMENTO)',
            'J3' => 'OBSERVAÇÃO',
            'K3' => 'Nº - NF',
            'L3' => 'DATA DE REGISTRO',
            'M3' => 'VALOR - ITEM',
            'N3' => 'VIDA ÚTIL (MESES)',
            'O3' => 'VIDA RESTANTE (MESES)',
            'P3' => 'DEPRECIAÇÃO',
        ];

        foreach ($headers as $cell => $value) {
            $sourceSheet->setCellValue($cell, $value);
        }

        $rowNumber = 4;

        foreach ($rows as $row) {
            $sourceSheet->setCellValue("B{$rowNumber}", $row['plate']);
            $sourceSheet->setCellValue("C{$rowNumber}", 'ANADEM');
            $sourceSheet->setCellValue("D{$rowNumber}", $row['item']);
            $sourceSheet->setCellValue("E{$rowNumber}", $row['category']);
            $sourceSheet->setCellValue("F{$rowNumber}", $row['description']);
            $sourceSheet->setCellValue("G{$rowNumber}", $row['status']);
            $sourceSheet->setCellValue("H{$rowNumber}", $row['collaborator']);
            $sourceSheet->setCellValue("I{$rowNumber}", $row['sector']);
            $sourceSheet->setCellValue("J{$rowNumber}", $row['observation']);
            $sourceSheet->setCellValue("K{$rowNumber}", $row['invoice']);
            $sourceSheet->setCellValue("L{$rowNumber}", $row['registered_at']);
            $sourceSheet->setCellValue("M{$rowNumber}", $row['value']);
            $sourceSheet->setCellValue("N{$rowNumber}", $row['useful_life']);
            $sourceSheet->setCellValue("O{$rowNumber}", $row['remaining_life']);
            $sourceSheet->setCellValue("P{$rowNumber}", $row['depreciation']);
            $rowNumber++;
        }

        $directory = storage_path('framework/testing/assets-import');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.uniqid('legacy_assets_', true).'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function largeWorkbookRows(): array
    {
        $rows = [
            [
                'plate' => 'PLACA-0001',
                'item' => 'Notebook TI',
                'category' => '04_AN_Equip. de proc',
                'description' => 'https://anadem.test/notebook-ti',
                'status' => 'ATIVO',
                'collaborator' => 'Uso de Todos do Setor',
                'sector' => 'TI',
                'observation' => 'Notebook do setor de TI',
                'invoice' => 'NF-0001',
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-15')),
                'value' => 4500.75,
                'useful_life' => null,
                'remaining_life' => 48,
                'depreciation' => 900.15,
            ],
            [
                'plate' => 'PLACA-0002',
                'item' => 'Monitor Compras',
                'category' => '05_AN_MÓVEIS E UTENS',
                'description' => 'https://anadem.test/monitor-compras',
                'status' => 'ATIVO',
                'collaborator' => 'Maria Souza',
                'sector' => 'COMPRAS',
                'observation' => 'Monitor em uso na equipe de compras',
                'invoice' => 'NF-0002',
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-16')),
                'value' => 1600.00,
                'useful_life' => 360,
                'remaining_life' => 300,
                'depreciation' => 220.00,
            ],
            [
                'plate' => 'PLACA-0003',
                'item' => 'Armario sem match',
                'category' => '05_AN_MÓVEIS E UTENS',
                'description' => '',
                'status' => 'BAIXADO',
                'collaborator' => '',
                'sector' => 'Centro de Eventos Anadem',
                'observation' => 'Sem correspondencia automatica de setor',
                'invoice' => 'NF-0003',
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-17')),
                'value' => 900.00,
                'useful_life' => 120,
                'remaining_life' => 0,
                'depreciation' => 900.00,
            ],
            [
                'plate' => 'PLACA-0004',
                'item' => 'Mouse sem status',
                'category' => '04_AN_Equip. de proc',
                'description' => '',
                'status' => '',
                'collaborator' => '',
                'sector' => 'FINANCEIRO',
                'observation' => 'Linha sem status legado',
                'invoice' => 'NF-0004',
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-18')),
                'value' => 80.00,
                'useful_life' => null,
                'remaining_life' => 12,
                'depreciation' => 10.00,
            ],
        ];

        $nextCode = 5;
        $activeSectors = ['TI', 'COMPRAS', 'FINANCEIRO', 'JURÍDICO', 'COMERCIAL'];
        $genericCollaborators = ['Uso Livre', 'Estoque', 'Uso de Todos do Setor'];

        for ($i = 0; $i < 996; $i++) {
            $rows[] = [
                'plate' => sprintf('PLACA-%04d', $nextCode),
                'item' => 'Ativo legado '.$nextCode,
                'category' => $i % 3 === 0 ? '03_AN_Máquinas e Eq' : '04_AN_Equip. de proc',
                'description' => 'https://anadem.test/item-'.$nextCode,
                'status' => 'ATIVO',
                'collaborator' => $i % 2 === 0 ? 'Colaborador '.$nextCode : $genericCollaborators[$i % count($genericCollaborators)],
                'sector' => $activeSectors[$i % count($activeSectors)],
                'observation' => 'Carga ativa '.$nextCode,
                'invoice' => 'NF-'.sprintf('%04d', $nextCode),
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-01')),
                'value' => 1000 + $nextCode,
                'useful_life' => null,
                'remaining_life' => 24,
                'depreciation' => 150.00,
            ];
            $nextCode++;
        }

        for ($i = 0; $i < 51; $i++) {
            $rows[] = [
                'plate' => sprintf('PLACA-%04d', $nextCode),
                'item' => 'Baixado legado '.$nextCode,
                'category' => '05_AN_MÓVEIS E UTENS',
                'description' => '',
                'status' => 'BAIXADO',
                'collaborator' => '',
                'sector' => $i % 2 === 0 ? 'Centro de Eventos Anadem' : 'COMERCIAL',
                'observation' => 'Carga baixada '.$nextCode,
                'invoice' => 'NF-'.sprintf('%04d', $nextCode),
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-02')),
                'value' => 500 + $nextCode,
                'useful_life' => 36,
                'remaining_life' => 0,
                'depreciation' => 500.00,
            ];
            $nextCode++;
        }

        for ($i = 0; $i < 949; $i++) {
            $rows[] = [
                'plate' => sprintf('PLACA-%04d', $nextCode),
                'item' => 'Pendente legado '.$nextCode,
                'category' => '04_AN_Equip. de proc',
                'description' => '',
                'status' => '',
                'collaborator' => '',
                'sector' => $activeSectors[$i % count($activeSectors)],
                'observation' => 'Carga pendente '.$nextCode,
                'invoice' => 'NF-'.sprintf('%04d', $nextCode),
                'registered_at' => ExcelDate::dateTimeToExcel(new \DateTimeImmutable('2026-04-03')),
                'value' => 300 + $nextCode,
                'useful_life' => null,
                'remaining_life' => 18,
                'depreciation' => 25.00,
            ];
            $nextCode++;
        }

        $this->assertCount(2000, $rows);

        return $rows;
    }
}
