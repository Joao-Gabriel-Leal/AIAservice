<?php

namespace App\Modules\Tickets\Services;

use App\Enums\TicketPriority;
use App\Modules\Sectors\Models\Sector;
use App\Modules\Tickets\Models\ServiceCatalogItem;
use App\Modules\Tickets\Models\TicketBoard;
use App\Modules\Tickets\Models\TicketForm;
use App\Modules\Tickets\Models\TicketGroup;
use App\Modules\Tickets\Models\TicketStatus;

class SectorProvisioningService
{
    public function __construct(
        private readonly TicketSlaService $ticketSlaService,
    ) {
    }

    public function provision(Sector $sector): TicketBoard
    {
        $board = TicketBoard::query()->firstOrCreate(
            ['sector_id' => $sector->id],
            [
                'name' => "Quadro {$sector->name}",
                'description' => "Quadro padrão do setor {$sector->name}.",
                'is_active' => true,
            ],
        );

        $groups = collect([
            ['name' => 'Aberto', 'slug' => 'aberto', 'color' => '#2563eb', 'sort_order' => 1],
            ['name' => 'Em andamento', 'slug' => 'em-andamento', 'color' => '#f59e0b', 'sort_order' => 2],
            ['name' => 'Finalizado', 'slug' => 'finalizado', 'color' => '#10b981', 'sort_order' => 3],
        ])->map(function (array $data) use ($board) {
            return TicketGroup::query()->firstOrCreate(
                ['ticket_board_id' => $board->id, 'slug' => $data['slug']],
                $data,
            );
        });

        collect([
            ['name' => 'Novo', 'slug' => 'novo', 'color' => '#2563eb', 'sort_order' => 1, 'is_default' => true, 'is_closed' => false],
            ['name' => 'Em atendimento', 'slug' => 'em-atendimento', 'color' => '#f59e0b', 'sort_order' => 2, 'is_default' => false, 'is_closed' => false],
            ['name' => 'Resolvido', 'slug' => 'resolvido', 'color' => '#10b981', 'sort_order' => 3, 'is_default' => false, 'is_closed' => true],
        ])->each(fn (array $data) => TicketStatus::query()->firstOrCreate(
            ['ticket_board_id' => $board->id, 'slug' => $data['slug']],
            $data,
        ));

        $defaultForm = TicketForm::query()->firstOrCreate(
            ['ticket_board_id' => $board->id, 'name' => 'Abertura padrão'],
            [
                'description' => 'Formulário padrão para abertura de chamados.',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        ServiceCatalogItem::query()->firstOrCreate(
            ['ticket_board_id' => $board->id, 'name' => 'Solicitação geral'],
            [
                'ticket_form_id' => $defaultForm->id,
                'description' => 'Catálogo padrão para demandas gerais.',
                'default_ticket_group_id' => $groups->first()?->id,
                'default_priority' => TicketPriority::MEDIUM,
                'is_active' => true,
            ],
        );

        $this->ticketSlaService->ensurePolicy($board);

        return $board->fresh(['groups', 'statuses', 'forms', 'catalogItems']);
    }
}
