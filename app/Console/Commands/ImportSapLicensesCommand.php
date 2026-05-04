<?php

namespace App\Console\Commands;

use App\Modules\Licenses\Services\SapLicenseImportService;
use Illuminate\Console\Command;

class ImportSapLicensesCommand extends Command
{
    protected $signature = 'licenses:import-sap {path : Caminho absoluto do arquivo XLSX SAP}';

    protected $description = 'Importa a planilha SAP para o modulo de licencas por setor.';

    public function handle(SapLicenseImportService $service): int
    {
        $path = (string) $this->argument('path');

        $this->components->info('Iniciando importacao da planilha SAP...');

        $summary = $service->import($path);

        $this->table(
            ['Aba', 'Setor', 'Linhas ativas', 'Bloqueadas ignoradas', 'Usuarios novos', 'Usuarios reutilizados'],
            [[
                $summary['sheet'],
                $summary['sector'],
                $summary['processed_rows'],
                $summary['ignored_blocked_rows'],
                $summary['created_users'],
                $summary['reused_users'],
            ]]
        );

        $this->table(
            ['Tipo de licenca', 'Assentos totais', 'Vinculos ativos'],
            collect($summary['licenses'])
                ->map(fn (array $licenseSummary, string $licenseType) => [
                    $licenseType,
                    $licenseSummary['seats_total'],
                    $licenseSummary['active_assignments'],
                ])
                ->values()
                ->all(),
        );

        $this->table(
            ['Vinculos criados', 'Vinculos atualizados', 'Vinculos liberados'],
            [[
                $summary['created_assignments'],
                $summary['updated_assignments'],
                $summary['released_assignments'],
            ]]
        );

        $this->line('Planilha importada de: '.$summary['path']);
        $this->components->info('Importacao SAP concluida com sucesso.');

        return self::SUCCESS;
    }
}
