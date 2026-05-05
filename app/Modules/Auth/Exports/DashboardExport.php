<?php

namespace App\Modules\Auth\Exports;

use App\Support\Exports\ExcelFileName;
use App\Support\Exports\ExcelSheetData;

class DashboardExport
{
    public function __construct(
        private readonly array $data,
    ) {}

    public function fileName(): string
    {
        return ExcelFileName::make('dashboard');
    }

    /**
     * @return array<int, ExcelSheetData>
     */
    public function sheets(): array
    {
        return [
            $this->summarySheet(),
            $this->attentionSheet(),
            $this->recentTicketsSheet(),
            $this->dailyVolumeSheet(),
            $this->statusDistributionSheet(),
            $this->priorityDistributionSheet(),
            $this->licenseSheet(),
            $this->assetSheet(),
            $this->knowledgeBaseSheet(),
        ];
    }

    private function summarySheet(): ExcelSheetData
    {
        return new ExcelSheetData(
            'Resumo',
            ['Indicador', 'Valor'],
            collect([
                ['Periodo', $this->data['period'].' dias'],
                ['Setor filtrado', $this->selectedSectorLabel()],
                ['Total de chamados', $this->data['stats']['tickets_total']],
                ['Chamados abertos', $this->data['stats']['open_tickets']],
                ['Criados no periodo', $this->data['stats']['created_in_period']],
                ['Resolvidos no periodo', $this->data['stats']['resolved_in_period']],
                ['Taxa de resolucao', $this->data['stats']['resolution_rate'].'%'],
                ['Com atividade recente', $this->data['stats']['active_in_period']],
                ['Sem responsavel', $this->data['stats']['unassigned_open_tickets']],
                ['SLA em atraso', $this->data['stats']['overdue_sla']],
                ['SLA perto do prazo', $this->data['stats']['warning_sla']],
                ['Alta ou urgente', $this->data['stats']['high_priority_open_tickets']],
                ['Sem atividade recente', $this->data['stats']['stale_open_tickets']],
                ['Setores no escopo', $this->data['stats']['sectors']],
                ['Colaboradores no escopo', $this->data['stats']['collaborators']],
                ['Empresas no escopo', $this->data['stats']['companies'] ?? 'N/A'],
                ['Media de avaliacao', $this->data['ratingSummary']['average'] ?? 'N/A'],
                ['Chamados avaliados', $this->data['ratingSummary']['rated_count'] ?? 'N/A'],
                ['Encerrados sem avaliacao', $this->data['ratingSummary']['pending_count'] ?? 'N/A'],
                ['Tempo aprovado no periodo', $this->data['timeTrackingSummary']['approved_human']],
                ['Apontamentos rodando', $this->data['timeTrackingSummary']['running_count']],
                ['Apontamentos manuais pendentes', $this->data['timeTrackingSummary']['pending_manual_count']],
                ['Licencas ativas', $this->data['licenseSummary']['can_view'] ? $this->data['licenseSummary']['active'] : 'N/A'],
                ['Licencas vencidas', $this->data['licenseSummary']['can_view'] ? $this->data['licenseSummary']['expired'] : 'N/A'],
                ['Licencas vencendo em 30 dias', $this->data['licenseSummary']['can_view'] ? $this->data['licenseSummary']['expiring_soon'] : 'N/A'],
                ['Assentos em uso', $this->data['licenseSummary']['can_view'] ? $this->data['licenseSummary']['seats_used'] : 'N/A'],
                ['Ativos no escopo', $this->data['assetSummary']['total']],
                ['Ativos em manutencao', $this->data['assetSummary']['in_maintenance']],
                ['Ativos extraviados', $this->data['assetSummary']['lost']],
                ['Ativos pendentes de saneamento', $this->data['assetSummary']['pending_review']],
                ['Artigos publicados', $this->data['knowledgeBaseSummary']['published']],
                ['Rascunhos para revisao', $this->data['knowledgeBaseSummary']['drafts']],
                ['Artigos gerados de chamados', $this->data['knowledgeBaseSummary']['generated_from_tickets']],
            ]),
        );
    }

    private function attentionSheet(): ExcelSheetData
    {
        return new ExcelSheetData(
            'Fila de atencao',
            ['Titulo', 'Motivo', 'Solicitante', 'Setor', 'Status', 'Prioridade', 'Responsavel', 'Atualizado em'],
            $this->data['attentionQueue']->map(fn (array $item) => [
                $item['ticket']->title,
                $item['reason'],
                $item['ticket']->requester?->name ?? 'N/A',
                $item['ticket']->sector?->name ?? 'Sem setor',
                $item['ticket']->status?->name ?? 'Sem status',
                $item['ticket']->priority?->label() ?? 'Sem prioridade',
                $item['ticket']->assignee?->name ?? 'Nao atribuido',
                $item['ticket']->updated_at?->format('d/m/Y H:i'),
            ]),
        );
    }

    private function recentTicketsSheet(): ExcelSheetData
    {
        return new ExcelSheetData(
            'Chamados recentes',
            ['Titulo', 'Solicitante', 'Setor', 'Status', 'Responsavel', 'Avaliacao', 'Atualizado em'],
            $this->data['recentTickets']->map(fn ($ticket) => [
                $ticket->title,
                $ticket->requester?->name ?? 'N/A',
                $ticket->sector?->name ?? 'Sem setor',
                $ticket->status?->name ?? 'Sem status',
                $ticket->assignee?->name ?? 'Nao atribuido',
                $ticket->rating?->rating ?? '',
                $ticket->updated_at?->format('d/m/Y H:i'),
            ]),
        );
    }

    private function dailyVolumeSheet(): ExcelSheetData
    {
        return new ExcelSheetData(
            'Volume por dia',
            ['Data', 'Criados', 'Resolvidos'],
            $this->data['chartDates']->map(fn ($date) => [
                $date->format('d/m/Y'),
                (int) ($this->data['createdByDay'][$date->toDateString()] ?? 0),
                (int) ($this->data['resolvedByDay'][$date->toDateString()] ?? 0),
            ]),
        );
    }

    private function statusDistributionSheet(): ExcelSheetData
    {
        return new ExcelSheetData(
            'Distribuicao por status',
            ['Status', 'Cor', 'Total'],
            $this->data['statusDistribution']->map(fn ($row) => [
                $row->status_name,
                $row->status_color,
                (int) $row->total,
            ]),
        );
    }

    private function priorityDistributionSheet(): ExcelSheetData
    {
        return new ExcelSheetData(
            'Distribuicao por prioridade',
            ['Prioridade', 'Cor', 'Total'],
            $this->data['priorityDistribution']->map(fn (array $row) => [
                $row['label'],
                $row['color'],
                (int) $row['total'],
            ]),
        );
    }

    private function licenseSheet(): ExcelSheetData
    {
        return new ExcelSheetData(
            'Licencas',
            ['Produto', 'Setor', 'Assentos', 'Em uso', 'Disponiveis', 'Vencimento/Renovacao'],
            $this->data['licenseSummary']['can_view']
                ? $this->data['licenseSummary']['upcoming']->map(fn ($license) => [
                    $license->displayName(),
                    $license->sector?->name ?? 'Sem setor',
                    (int) $license->seats_total,
                    (int) $license->active_assignments_count,
                    $license->seatsAvailable(),
                    $license->dueDate()?->format('d/m/Y') ?? 'Sem data',
                ])
                : collect([['Sem acesso ao modulo de licencas', '', '', '', '', '']]),
        );
    }

    private function assetSheet(): ExcelSheetData
    {
        return new ExcelSheetData(
            'Ativos',
            ['Patrimonio', 'Nome', 'Estado', 'Alocacao', 'Setor', 'Sala', 'Responsavel'],
            $this->data['assetSummary']['attention_assets']->map(fn ($asset) => [
                $asset->asset_code ?? 'Sem codigo',
                $asset->name,
                $asset->statusLabel(),
                $asset->allocationStatusLabel(),
                $asset->currentSector?->name ?? 'Sem setor',
                $asset->currentRoom?->name ?? 'Sem sala',
                $asset->currentUser?->name ?? 'Sem responsavel',
            ]),
        );
    }

    private function knowledgeBaseSheet(): ExcelSheetData
    {
        $reviewRows = $this->data['knowledgeBaseSummary']['review_articles']
            ->map(fn ($article) => [
                'Revisao',
                $article->title,
                $article->sector?->name ?? 'Sem setor',
                $article->author?->name ?? 'N/A',
                $article->updated_at?->format('d/m/Y H:i'),
                '',
            ]);

        $topRows = $this->data['knowledgeBaseSummary']['top_articles']
            ->map(fn ($article) => [
                'Mais usado',
                $article->title,
                $article->sector?->name ?? 'Sem setor',
                '',
                $article->updated_at?->format('d/m/Y H:i'),
                (int) $article->ticket_usages_count,
            ]);

        return new ExcelSheetData(
            'Base de conhecimento',
            ['Tipo', 'Titulo', 'Setor', 'Autor', 'Atualizado em', 'Usos em chamados'],
            $reviewRows->concat($topRows)->values(),
        );
    }

    private function selectedSectorLabel(): string
    {
        $sector = $this->data['availableSectors']
            ->firstWhere('id', $this->data['selectedSectorId']);

        return $sector?->name ?? 'Todos';
    }
}
