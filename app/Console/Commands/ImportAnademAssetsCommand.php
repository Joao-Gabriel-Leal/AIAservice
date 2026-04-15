<?php

namespace App\Console\Commands;

use App\Modules\Assets\Services\LegacyAssetImportService;
use Illuminate\Console\Command;

class ImportAnademAssetsCommand extends Command
{
    protected $signature = 'assets:import-anadem {path : Caminho absoluto do arquivo XLSX legado}';

    protected $description = 'Importa a aba Anadem para staging patrimonial e promove os itens prontos para o modulo de ativos.';

    public function handle(LegacyAssetImportService $service): int
    {
        $path = (string) $this->argument('path');

        $this->components->info('Iniciando leitura patrimonial da aba Anadem...');

        $batch = $service->import($path);

        $this->table(
            ['Batch', 'Linhas', 'Ready', 'Pendentes', 'Erros', 'Promovidos'],
            [[
                $batch->id,
                $batch->total_rows,
                $batch->ready_rows,
                $batch->pending_review_rows,
                $batch->error_rows,
                $batch->promoted_rows,
            ]]
        );

        $this->line('Planilha importada de: '.$batch->source_path);
        $this->line('Aba principal: '.$batch->source_sheet.' | referencia: '.$batch->reference_sheet);
        $this->components->info('Importacao patrimonial concluida com sucesso.');

        return self::SUCCESS;
    }
}
