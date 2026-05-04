<?php

namespace Tests\Feature\Management;

use App\Enums\GlobalUserRole;
use App\Enums\LicenseAssignmentStatus;
use App\Enums\UserRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Licenses\Models\License;
use App\Modules\Licenses\Models\LicenseAssignment;
use App\Modules\Sectors\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LicenseImportCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    public function test_sap_import_creates_four_license_types_and_is_idempotent(): void
    {
        $this->sapImportContext();
        $workbook = $this->createWorkbook([
            ['code' => 'SAP001', 'name' => 'Alice Silva', 'blocked' => 'Nao', 'professional' => 'Sim', 'financials' => '', 'logistics' => 'Sim', 'indirect' => ''],
            ['code' => 'SAP002', 'name' => 'Bruno Costa', 'blocked' => 'Sim', 'professional' => 'Sim', 'financials' => '', 'logistics' => '', 'indirect' => ''],
            ['code' => 'SAP003', 'name' => 'Carla Souza', 'blocked' => 'Nao', 'professional' => '', 'financials' => 'Sim', 'logistics' => '', 'indirect' => ''],
            ['code' => 'SAP004', 'name' => 'Diego Lima', 'blocked' => 'Nao', 'professional' => '', 'financials' => '', 'logistics' => '', 'indirect' => ''],
        ]);

        $this->artisan('licenses:import-sap', ['path' => $workbook])->assertSuccessful();

        $this->assertDatabaseCount('licenses', 4);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('license_assignments', 3);

        $this->assertDatabaseHas('licenses', [
            'vendor_name' => 'SAP',
            'product_name' => 'SAP Business One',
            'plan_name' => 'Profissional',
            'seats_total' => 1,
        ]);

        $this->assertDatabaseHas('licenses', [
            'vendor_name' => 'SAP',
            'product_name' => 'SAP Business One',
            'plan_name' => 'Limited Financials',
            'seats_total' => 1,
        ]);

        $this->assertDatabaseHas('licenses', [
            'vendor_name' => 'SAP',
            'product_name' => 'SAP Business One',
            'plan_name' => 'Limited Logistics',
            'seats_total' => 1,
        ]);

        $this->assertDatabaseHas('licenses', [
            'vendor_name' => 'SAP',
            'product_name' => 'SAP Business One',
            'plan_name' => 'Indirect Access',
            'seats_total' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'sap.sap001@aia-tech.local',
            'name' => 'Alice Silva',
            'role' => UserRole::REQUESTER->value,
            'global_role' => GlobalUserRole::COLLABORATOR->value,
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'sap.sap003@aia-tech.local',
            'name' => 'Carla Souza',
            'role' => UserRole::REQUESTER->value,
            'global_role' => GlobalUserRole::COLLABORATOR->value,
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'sap.sap002@aia-tech.local',
        ]);

        $this->assertDatabaseHas('license_assignments', [
            'external_reference' => 'SAP001',
            'status' => LicenseAssignmentStatus::ACTIVE->value,
            'seat_label' => 'Profissional',
        ]);

        $this->assertDatabaseHas('license_assignments', [
            'external_reference' => 'SAP001',
            'status' => LicenseAssignmentStatus::ACTIVE->value,
            'seat_label' => 'Limited Logistics',
        ]);

        $this->assertDatabaseHas('license_assignments', [
            'external_reference' => 'SAP003',
            'status' => LicenseAssignmentStatus::ACTIVE->value,
            'seat_label' => 'Limited Financials',
        ]);

        $this->artisan('licenses:import-sap', ['path' => $workbook])->assertSuccessful();

        $this->assertDatabaseCount('licenses', 4);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('license_assignments', 3);

        $licenses = License::query()->orderBy('plan_name')->get();

        $this->assertSame([0, 1, 1, 1], $licenses->pluck('seats_total')->sort()->values()->all());
        $this->assertSame(3, LicenseAssignment::query()->where('status', LicenseAssignmentStatus::ACTIVE->value)->count());
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

    private function sapImportContext(): void
    {
        $company = Company::query()->create([
            'name' => 'Empresa SAP',
            'is_active' => true,
        ]);

        Sector::query()->create([
            'company_id' => $company->id,
            'name' => 'AIA Tech',
            'slug' => 'aia-tech',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function createWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Planilha1');

        $headers = [
            'A1' => 'Codigo do usuario',
            'B1' => 'Nome do usuario',
            'C1' => 'Bloqueado',
            'D1' => 'Profissional',
            'E1' => 'Limited Financials',
            'F1' => 'Limited Logistics',
            'G1' => 'Indirect Access',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $rowNumber = 2;

        foreach ($rows as $row) {
            $sheet->setCellValue("A{$rowNumber}", $row['code']);
            $sheet->setCellValue("B{$rowNumber}", $row['name']);
            $sheet->setCellValue("C{$rowNumber}", $row['blocked']);
            $sheet->setCellValue("D{$rowNumber}", $row['professional']);
            $sheet->setCellValue("E{$rowNumber}", $row['financials']);
            $sheet->setCellValue("F{$rowNumber}", $row['logistics']);
            $sheet->setCellValue("G{$rowNumber}", $row['indirect']);
            $rowNumber++;
        }

        $directory = storage_path('framework/testing/licenses-import');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.'/'.uniqid('sap_licenses_', true).'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $this->temporaryFiles[] = $path;

        return $path;
    }
}
