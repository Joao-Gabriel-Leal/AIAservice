<?php

namespace App\Console\Commands;

use App\Modules\Users\Services\LegacyUserImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportLegacyUsersCommand extends Command
{
    protected $signature = 'users:import-legacy
        {path : Caminho absoluto do arquivo XLSX legado}
        {--password=AiaService@2026! : Senha temporaria definida para usuarios importados}
        {--dry-run : Simula a importacao em transacao e desfaz no final}';

    protected $description = 'Importa setores, usuarios, acessos e chamados demo a partir da planilha legado.';

    public function handle(LegacyUserImportService $service): int
    {
        $path = (string) $this->argument('path');
        $password = (string) $this->option('password');
        $dryRun = (bool) $this->option('dry-run');

        if (strlen($password) < 8) {
            $this->components->error('A senha temporaria precisa ter pelo menos 8 caracteres.');

            return self::FAILURE;
        }

        $this->components->info(($dryRun ? 'Simulando' : 'Iniciando').' importacao legado de usuarios...');

        try {
            $summary = $service->import($path, $password, $dryRun);
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Fonte', 'Linhas/qtde'],
            [
                ['Planilha1 logins', $summary['source']['login_rows']],
                ['Planilha2 usuarios', $summary['source']['user_rows']],
                ['Planilha3 setores', $summary['source']['sector_rows']],
                ['Setores unicos', $summary['source']['sector_names']],
                ['Emails duplicados em usuarios', $summary['source']['user_duplicate_emails']],
                ['Emails duplicados em logins', $summary['source']['login_duplicate_emails']],
            ],
        );

        $this->table(
            ['Area', 'Criados', 'Atualizados', 'Reativados', 'Extras'],
            [
                ['Empresa', $summary['company']['created'], $summary['company']['updated'], $summary['company']['reactivated'], $summary['company']['name']],
                ['Setores', $summary['sectors']['created'], $summary['sectors']['updated'], $summary['sectors']['reactivated'], 'provisionados: '.$summary['sectors']['provisioned']],
                ['Usuarios', $summary['users']['created'], $summary['users']['updated'], $summary['users']['reactivated'], 'sem setor: '.$summary['users']['without_sector'].' | super admins preservados: '.$summary['users']['preserved_super_admin']],
                ['Acessos', $summary['accesses']['created'], $summary['accesses']['updated'], 0, 'removidos: '.$summary['accesses']['removed']],
                ['Chamados demo', $summary['demo']['tickets_created'], $summary['demo']['tickets_updated'], 0, 'mensagens criadas: '.$summary['demo']['messages_created']],
            ],
        );

        $this->table(
            ['Campo legado ignorado', 'Linhas com valor'],
            collect($summary['ignored_fields'])
                ->map(fn (int $count, string $field) => [$field, $count])
                ->values()
                ->all(),
        );

        $this->components->info($dryRun ? 'Dry-run concluido sem gravar alteracoes.' : 'Importacao legado concluida com sucesso.');

        return self::SUCCESS;
    }
}
