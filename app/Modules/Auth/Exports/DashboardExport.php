<?php

namespace App\Modules\Auth\Exports;

use App\Support\Exports\ExcelFileName;
use App\Support\Exports\ExcelSheetData;

class DashboardExport
{
    public function __construct(
        private readonly array $data,
    ) {
    }

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
            new ExcelSheetData(
                'Resumo',
                ['Indicador', 'Valor'],
                collect([
                    ['Periodo', $this->data['period'].' dias'],
                    ['Total de chamados', $this->data['stats']['tickets_total']],
                    ['Chamados abertos', $this->data['stats']['open_tickets']],
                    ['Criados no periodo', $this->data['stats']['created_in_period']],
                    ['Resolvidos no periodo', $this->data['stats']['resolved_in_period']],
                    ['Com atividade recente', $this->data['stats']['active_in_period']],
                    ['Sem responsavel', $this->data['stats']['unassigned_open_tickets']],
                    ['SLA em atraso', $this->data['stats']['overdue_sla']],
                    ['Setores no escopo', $this->data['stats']['sectors']],
                    ['Colaboradores no escopo', $this->data['stats']['collaborators']],
                    ['Empresas no escopo', $this->data['stats']['companies'] ?? 'N/A'],
                    ['Media de avaliacao', $this->data['ratingSummary']['average'] ?? 'N/A'],
                    ['Chamados avaliados', $this->data['ratingSummary']['rated_count'] ?? 'N/A'],
                    ['Encerrados sem avaliacao', $this->data['ratingSummary']['pending_count'] ?? 'N/A'],
                ]),
            ),
            new ExcelSheetData(
                'Chamados recentes',
                ['Titulo', 'Solicitante', 'Status', 'Responsavel', 'Avaliacao', 'Atualizado em'],
                $this->data['recentTickets']->map(fn ($ticket) => [
                    $ticket->title,
                    $ticket->requester?->name ?? 'N/A',
                    $ticket->status?->name ?? 'Sem status',
                    $ticket->assignee?->name ?? 'Nao atribuido',
                    $ticket->rating?->rating ?? '',
                    $ticket->updated_at?->format('d/m/Y H:i'),
                ]),
            ),
            new ExcelSheetData(
                'Volume por dia',
                ['Data', 'Criados', 'Resolvidos'],
                $this->data['chartDates']->map(fn ($date) => [
                    $date->format('d/m/Y'),
                    (int) ($this->data['createdByDay'][$date->toDateString()] ?? 0),
                    (int) ($this->data['resolvedByDay'][$date->toDateString()] ?? 0),
                ]),
            ),
            new ExcelSheetData(
                'Distribuicao por status',
                ['Status', 'Cor', 'Total'],
                $this->data['statusDistribution']->map(fn ($row) => [
                    $row->status_name,
                    $row->status_color,
                    (int) $row->total,
                ]),
            ),
            new ExcelSheetData(
                'Saude operacional',
                ['Indicador', 'Total'],
                collect([
                    ['Abertos', (int) $this->data['stats']['open_tickets']],
                    ['Sem responsavel', (int) $this->data['stats']['unassigned_open_tickets']],
                    ['SLA em atraso', (int) $this->data['stats']['overdue_sla']],
                ]),
            ),
        ];
    }
}
