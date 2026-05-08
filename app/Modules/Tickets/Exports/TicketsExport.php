<?php

namespace App\Modules\Tickets\Exports;

use App\Modules\Tickets\Models\TicketField;
use App\Support\Exports\ExcelFileName;
use App\Support\Exports\ExcelSheetData;
use Illuminate\Support\Collection;

class TicketsExport
{
    public function __construct(
        private readonly Collection $tickets,
        private readonly Collection $fields = new Collection(),
    ) {
    }

    public function fileName(): string
    {
        return ExcelFileName::make('atendimentos');
    }

    /**
     * @return array<int, ExcelSheetData>
     */
    public function sheets(): array
    {
        return [
            new ExcelSheetData(
                'Atendimentos',
                [
                    'Codigo',
                    'ID interno',
                    'Titulo',
                    'Setor',
                    'Empresa',
                    'Etapa',
                    'Prioridade',
                    'Solicitante',
                    'Responsavel',
                    'Atualizado em',
                    ...$this->fields->map(fn (TicketField $field) => $field->name)->all(),
                ],
                $this->tickets->map(function ($ticket) {
                    return [
                        $ticket->publicReference(),
                        $ticket->technicalReference(),
                        $ticket->title,
                        $ticket->sector?->name ?? 'Sem setor',
                        $ticket->sector?->company?->name ?? '',
                        $ticket->group?->name ?? 'Sem etapa',
                        $ticket->priority?->label() ?? '',
                        $ticket->requester?->name ?? 'Nao informado',
                        $ticket->assignee?->name ?? 'Nao atribuido',
                        $ticket->updated_at?->format('d/m/Y H:i'),
                        ...$this->fields->map(function (TicketField $field) use ($ticket) {
                            $value = $ticket->fieldValues->firstWhere('ticket_field_id', $field->id)?->primitive_value;

                            return is_bool($value) ? ($value ? 'Sim' : 'Nao') : (string) ($value ?? '');
                        })->all(),
                    ];
                }),
            ),
        ];
    }
}
